# Patch 001: Enhanced Customer Matching for MatchingService

**Apply to:** `ksf_FA_ImportStagingProcessing`

**Goal:** Replace basic `matchByCustomer()` with our scoring-based algorithm (0-100 scale, email=30, phone=25, name=20, address=15, Levenshtein fuzzy matching, auto-select threshold 50).

---

## File: `src/Services/MatchingService.php`

### A) Add constants and helper methods

After the `$reviewThreshold` property (around line 11), add:

```php
    private const CUSTOMER_MATCH_THRESHOLD = 50.0;

    private const CUSTOMER_SCORE_EMAIL = 30.0;
    private const CUSTOMER_SCORE_PHONE = 25.0;
    private const CUSTOMER_SCORE_NAME = 20.0;
    private const CUSTOMER_SCORE_CONTACT_NAME = 20.0;
    private const CUSTOMER_SCORE_ADDRESS = 15.0;
```

### B) Replace the matchByCustomer method (lines 123–139)

**Replace the entire method** with this implementation:

```php
    public function matchByCustomer(array $staged, array $existingRecords): array
    {
        $candidates = [];
        foreach ($existingRecords as $record) {
            $score = $this->calculateCustomerMatchScore($staged, $record);
            $candidates[] = [
                'debtor_no'  => $record['debtor_no'] ?? null,
                'branch_ref' => $record['branch_ref'] ?? null,
                'name'       => $record['name'] ?? null,
                'company'    => $record['company'] ?? $record['name'] ?? null,
                'email'      => $record['email'] ?? null,
                'phone'      => $record['phone'] ?? null,
                'score'      => $score,
            ];
        }
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
        return $candidates;
    }
```

### C) Add the scoring method

Add these new methods after the `exactMatch()` method (around line 169):

```php
    public function calculateCustomerMatchScore(array $staged, array $existing): float
    {
        $score = 0.0;

        // Email vs debtors_master.email (30 pts)
        if (!empty($staged['email']) && !empty($existing['email'])) {
            if (strcasecmp(trim($staged['email']), trim($existing['email'])) === 0) {
                $score += self::CUSTOMER_SCORE_EMAIL;
            }
        }

        // Email vs branches.email (30 pts) — cumulative with master email
        if (!empty($staged['email']) && !empty($existing['branch_email'])) {
            if (strcasecmp(trim($staged['email']), trim($existing['branch_email'])) === 0) {
                $score += self::CUSTOMER_SCORE_EMAIL;
            }
        }

        // Phone vs branches.phone (25 pts) — digits-only
        if (!empty($staged['phone']) && !empty($existing['phone'])) {
            $cleanStaged = preg_replace('/[^0-9]/', '', $staged['phone']);
            $cleanExisting = preg_replace('/[^0-9]/', '', $existing['phone']);
            if ($cleanStaged !== '' && $cleanStaged === $cleanExisting) {
                $score += self::CUSTOMER_SCORE_PHONE;
            }
        }

        // Company/name vs debtors_master.name (20 pts)
        if (!empty($staged['company']) && !empty($existing['name'])) {
            if ($this->fuzzyMatch($staged['company'], $existing['name'])) {
                $score += self::CUSTOMER_SCORE_NAME;
            }
        }

        // Contact name vs branches.contact_name (20 pts)
        $stagedContact = trim(($staged['first_name'] ?? '') . ' ' . ($staged['last_name'] ?? ''));
        if ($stagedContact !== '' && !empty($existing['contact_name'])) {
            if ($this->fuzzyMatch($stagedContact, $existing['contact_name'])) {
                $score += self::CUSTOMER_SCORE_CONTACT_NAME;
            }
        }

        // Address vs branches.br_address (15 pts) — substring containment
        if (!empty($staged['address1']) && !empty($existing['br_address'])) {
            if ($this->addressMatch($staged['address1'], $existing['br_address'])) {
                $score += self::CUSTOMER_SCORE_ADDRESS;
            }
        }

        return min(100.0, $score);
    }

    public function fuzzyMatch(string $a, string $b): bool
    {
        $a = strtolower(trim(preg_replace('/\s+/', ' ', $a)));
        $b = strtolower(trim(preg_replace('/\s+/', ' ', $b)));

        if ($a === $b) {
            return true;
        }
        if (str_contains($a, $b) || str_contains($b, $a)) {
            return true;
        }

        $dist = levenshtein($a, $b);
        $len = max(strlen($a), strlen($b));
        return $len > 0 && ($dist / $len) < 0.2;
    }

    public function addressMatch(string $addrA, string $addrB): bool
    {
        $normA = strtolower(trim(preg_replace('/\s+/', ' ', $addrA)));
        $normB = strtolower(trim(preg_replace('/\s+/', ' ', $addrB)));
        return str_contains($normA, $normB) || str_contains($normB, $normA);
    }
```

---

## Caller expectations

The method now returns a **sorted array of candidate arrays** (not a simple float). Each candidate has:

```php
[
    'debtor_no'  => int|null,
    'branch_ref' => string|null,
    'name'       => string|null,
    'company'    => string|null,
    'email'      => string|null,
    'phone'      => string|null,
    'score'      => float,   // 0.0 – 100.0
]
```

- **Auto-select**: candidate with highest score ≥ `self::CUSTOMER_MATCH_THRESHOLD` (50.0)
- **Needs review**: candidate with highest score > 0 but < 50.0
- **Unmatched**: no candidates or highest score = 0.0

---

## Tests to add (optional)

Add these test cases to the existing test suite:

1. Exact email match → score = 30
2. Email + phone match → score = 55 (auto-select)
3. Email + name fuzzy match (substring) → score = 50 (auto-select)
4. Address substring containment match → score = 15
5. No match at all → score = 0.0, empty candidates
6. Levenshtein close match (e.g., "ACME Corp" vs "ACME Corporation") → fuzzyMatch true
7. Different phone formats (555-0100 vs 5550100) → match

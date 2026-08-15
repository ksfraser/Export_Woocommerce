<?php
namespace ksfraser\FrontAccounting\Woocommerce\Staging;

use ksfraser\FrontAccounting\Woocommerce\DatabaseInterface;
use ksfraser\FrontAccounting\Woocommerce\LoggerInterface;

class CustomerStaging
{
    private $db;
    private $logger;

    private const HOOK_MODULE = 'ksf_FA_ImportStagingProcessing';

    private const SCORE_EMAIL = 30.0;
    private const SCORE_PHONE = 25.0;
    private const SCORE_NAME_COMPANY = 20.0;
    private const SCORE_ADDRESS = 15.0;
    private const SCORE_HIGH_MATCH = 50.0;

    public function __construct(DatabaseInterface $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    private function callStagingHook(string $action, array $params = []): ?array
    {
        if (!function_exists('hook_invoke')) {
            return null;
        }
        $data = [];
        $params['request'] = 'staging:' . $action;
        hook_invoke(self::HOOK_MODULE, 'respondToCapabilityRequest', $data, $params);
        if (!empty($data['success']) && isset($data['result'])) {
            return is_array($data['result']) ? $data['result'] : ['id' => $data['result']];
        }
        if (!empty($data['error'])) {
            $this->logger->warning('Staging hook error: ' . $data['error']);
        }
        return null;
    }

    public function stageCustomer(array $wooData): int
    {
        $billing = $wooData['billing'] ?? $wooData;

        $customerName = !empty($billing['company'])
            ? $billing['company']
            : trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));

        $this->callStagingHook('stageCustomer', [
            'source' => 'woocommerce',
            'customer' => [
                'source_customer_id' => (string)($billing['customer_id'] ?? $wooData['customer_id'] ?? 0),
                'name' => $customerName,
                'email' => $billing['email'] ?? '',
                'phone' => $billing['phone'] ?? '',
                'address_line1' => $billing['address_1'] ?? '',
                'address_line2' => $billing['address_2'] ?? '',
                'city' => $billing['city'] ?? '',
                'province' => $billing['state'] ?? '',
                'postal_code' => $billing['postcode'] ?? '',
                'country' => $billing['country'] ?? '',
                'raw_json' => json_encode($wooData),
            ],
        ]);

        return $this->stageCustomerLegacy($wooData);
    }

    private function stageCustomerLegacy(array $wooData): int
    {
        $prefix = $this->db->getPrefix();
        $billing = $wooData['billing'] ?? $wooData;

        $sql = sprintf(
            "INSERT INTO %swoo_customer_staging
            (woo_customer_id, woo_order_id, email, phone, first_name, last_name,
             company, address1, address2, city, state, postcode, country,
             raw_data, staged_at)
            VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', NOW())",
            $prefix,
            $this->db->escape($billing['customer_id'] ?? $wooData['customer_id'] ?? 0),
            $this->db->escape($wooData['id'] ?? 0),
            $this->db->escape($billing['email'] ?? ''),
            $this->db->escape($billing['phone'] ?? ''),
            $this->db->escape($billing['first_name'] ?? ''),
            $this->db->escape($billing['last_name'] ?? ''),
            $this->db->escape($billing['company'] ?? ''),
            $this->db->escape($billing['address_1'] ?? ''),
            $this->db->escape($billing['address_2'] ?? ''),
            $this->db->escape($billing['city'] ?? ''),
            $this->db->escape($billing['state'] ?? ''),
            $this->db->escape($billing['postcode'] ?? ''),
            $this->db->escape($billing['country'] ?? ''),
            $this->db->escape(json_encode($wooData))
        );

        $this->db->execute($sql);

        $result = $this->db->query("SELECT LAST_INSERT_ID() as id");
        return (int)($result[0]['id'] ?? 0);
    }

    public function findMatches(int $stagingId): array
    {
        $prefix = $this->db->getPrefix();

        $staged = $this->db->query(sprintf(
            "SELECT * FROM %swoo_customer_staging WHERE id = %d",
            $prefix, $stagingId
        ));

        if (empty($staged)) {
            return [];
        }

        $data = $staged[0];
        $matches = [];

        $sql = sprintf(
            "SELECT
                dm.debtor_no,
                dm.name,
                dm.email,
                dm.curr_code,
                bm.branch_ref,
                bm.br_name,
                bm.contact_name,
                bm.phone,
                bm.email as branch_email,
                bm.br_address
            FROM %sdebtors_master dm
            LEFT JOIN %sbranches bm ON dm.debtor_no = bm.debtor_ref
            WHERE 1=1",
            $prefix, $prefix
        );

        $candidates = $this->db->query($sql);

        foreach ($candidates as $candidate) {
            $score = $this->calculateMatchScore($data, $candidate);
            if ($score > 0) {
                $matches[] = [
                    'debtor_no' => $candidate['debtor_no'],
                    'branch_ref' => $candidate['branch_ref'] ?? '',
                    'name' => $candidate['name'],
                    'company' => $candidate['br_name'] ?? '',
                    'score' => $score,
                    'email' => $candidate['email'] ?? $candidate['branch_email'] ?? '',
                    'phone' => $candidate['phone'] ?? ''
                ];
            }
        }

        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

        return $matches;
    }

    private function calculateMatchScore(array $staged, array $candidate): float
    {
        $score = 0.0;

        if (!empty($staged['email']) && !empty($candidate['email'])) {
            if (strtolower($staged['email']) === strtolower($candidate['email'])) {
                $score += self::SCORE_EMAIL;
            }
        }
        if (!empty($staged['email']) && !empty($candidate['branch_email'])) {
            if (strtolower($staged['email']) === strtolower($candidate['branch_email'])) {
                $score += self::SCORE_EMAIL;
            }
        }

        if (!empty($staged['phone']) && !empty($candidate['phone'])) {
            $stagedPhone = preg_replace('/[^0-9]/', '', $staged['phone']);
            $candidatePhone = preg_replace('/[^0-9]/', '', $candidate['phone']);
            if ($stagedPhone === $candidatePhone && !empty($stagedPhone)) {
                $score += self::SCORE_PHONE;
            }
        }

        if (!empty($staged['company']) && !empty($candidate['name'])) {
            if ($this->fuzzyMatch($staged['company'], $candidate['name'])) {
                $score += self::SCORE_NAME_COMPANY;
            }
        }

        $contactName = trim(($staged['first_name'] ?? '') . ' ' . ($staged['last_name'] ?? ''));
        if (!empty($contactName) && !empty($candidate['contact_name'])) {
            if ($this->fuzzyMatch($contactName, $candidate['contact_name'])) {
                $score += self::SCORE_NAME_COMPANY;
            }
        }

        if (!empty($staged['address1'])) {
            $addr = $this->normalizeAddress($staged['address1']);
            $candidateAddr = $this->normalizeAddress($candidate['br_address'] ?? '');
            if (!empty($addr) && !empty($candidateAddr) && strpos($candidateAddr, $addr) !== false) {
                $score += self::SCORE_ADDRESS;
            }
        }

        return min(100.0, $score);
    }

    private function fuzzyMatch(string $a, string $b): bool
    {
        $a = strtolower(trim(preg_replace('/\s+/', ' ', $a)));
        $b = strtolower(trim(preg_replace('/\s+/', ' ', $b)));

        if ($a === $b) return true;
        if (strpos($a, $b) !== false || strpos($b, $a) !== false) return true;

        $dist = levenshtein($a, $b);
        $len = max(strlen($a), strlen($b));
        return $len > 0 && ($dist / $len) < 0.2;
    }

    private function normalizeAddress(string $addr): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $addr)));
    }

    public function importCustomer(int $stagingId, ?int $selectedDebtorNo = null, ?string $selectedBranch = null): array
    {
        $prefix = $this->db->getPrefix();

        $staged = $this->db->query(sprintf(
            "SELECT * FROM %swoo_customer_staging WHERE id = %d",
            $prefix, $stagingId
        ));

        if (empty($staged)) {
            return ['error' => 'Staged record not found'];
        }

        $data = $staged[0];
        $billing = json_decode($data['raw_data'], true)['billing'] ?? [];

        if ($selectedDebtorNo !== null) {
            $debtorNo = $selectedDebtorNo;

            if ($selectedBranch !== null) {
                $branchRef = $selectedBranch;
            } else {
                $branchRef = $this->createBranch($debtorNo, $billing);
            }
        } else {
            $debtorNo = $this->createCustomer($billing);
            $branchRef = $this->createBranch($debtorNo, $billing);
        }

        $this->db->execute(sprintf(
            "UPDATE %swoo_customer_staging SET imported = 1, imported_at = NOW(),
             fa_debtor_no = %d, fa_branch_ref = '%s' WHERE id = %d",
            $prefix, $debtorNo, $this->db->escape($branchRef), $stagingId
        ));

        return [
            'debtor_no' => $debtorNo,
            'branch_ref' => $branchRef
        ];
    }

    private function createCustomer(array $billing): int
    {
        $prefix = $this->db->getPrefix();

        $name = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
        if (!empty($billing['company'])) {
            $name = $billing['company'];
        }

        $sql = sprintf(
            "INSERT INTO %sdebtors_master
            (name, email, curr_code, tax_group_id)
            VALUES ('%s', '%s', 'USD', 1)",
            $prefix,
            $this->db->escape($name),
            $this->db->escape($billing['email'] ?? '')
        );

        $this->db->execute($sql);

        $result = $this->db->query("SELECT LAST_INSERT_ID() as id");
        return (int)($result[0]['id'] ?? 0);
    }

    private function createBranch(int $debtorNo, array $billing): string
    {
        $prefix = $this->db->getPrefix();

        $branchRef = 'BR-' . $debtorNo . '-' . substr(md5(uniqid()), 0, 6);
        $brName = !empty($billing['company']) ? $billing['company'] :
                  trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));

        $address = trim(($billing['address_1'] ?? '') . "\n" . ($billing['address_2'] ?? '') . "\n" .
                   ($billing['city'] ?? '') . ', ' . ($billing['state'] ?? '') . ' ' . ($billing['postcode'] ?? '') . "\n" .
                   ($billing['country'] ?? ''));

        $sql = sprintf(
            "INSERT INTO %sbranches
            (debtor_ref, branch_ref, br_name, contact_name, phone, email, br_address)
            VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s')",
            $prefix,
            $debtorNo,
            $this->db->escape($branchRef),
            $this->db->escape($brName),
            $this->db->escape(trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''))),
            $this->db->escape($billing['phone'] ?? ''),
            $this->db->escape($billing['email'] ?? ''),
            $this->db->escape($address)
        );

        $this->db->execute($sql);

        return $branchRef;
    }

    public function getStagedCustomers(): array
    {
        $prefix = $this->db->getPrefix();
        return $this->db->query(sprintf(
            "SELECT * FROM %swoo_customer_staging ORDER BY staged_at DESC",
            $prefix
        ));
    }

    public function ensureStagingTable(): void
    {
        $prefix = $this->db->getPrefix();
        $this->db->execute(sprintf(
            "CREATE TABLE IF NOT EXISTS %swoo_customer_staging (
                id INT AUTO_INCREMENT PRIMARY KEY,
                woo_customer_id INT,
                woo_order_id INT,
                email VARCHAR(255),
                phone VARCHAR(50),
                first_name VARCHAR(100),
                last_name VARCHAR(100),
                company VARCHAR(255),
                address1 VARCHAR(255),
                address2 VARCHAR(255),
                city VARCHAR(100),
                state VARCHAR(100),
                postcode VARCHAR(20),
                country VARCHAR(100),
                raw_data TEXT,
                imported TINYINT DEFAULT 0,
                imported_at DATETIME NULL,
                fa_debtor_no INT NULL,
                fa_branch_ref VARCHAR(100) NULL,
                staged_at DATETIME,
                INDEX idx_email (email),
                INDEX idx_woo_customer (woo_customer_id),
                INDEX idx_imported (imported)
            )",
            $prefix
        ));
    }
}

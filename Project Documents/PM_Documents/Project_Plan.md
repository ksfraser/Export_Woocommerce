# Export WooCommerce - Project Plan

## Project Overview
**Project Name**: Export WooCommerce Module Enhancement
**Objective**: Clean up existing code, complete variable product support, and enhance functionality

## Current State Assessment

### What Works
- Basic simple product export to WooCommerce
- Category export with mapping table
- QOH (Quantity on Hand) synchronization
- Price and special price export
- REST API integration (basic)

### What's Missing/Incomplete
- Variable product export (rudimentary)
- Order import/export (partial)
- Customer synchronization (partial)
- Code cleanup (mixed MVC, duplicate files)
- Comprehensive testing
- Documentation (now being addressed)

### Technical Debt
1. Duplicate files (class.EXPORT_WOO.php vs class.EXPORT_WOO - Copy.php)
2. Mixed MVC - GUI code in model classes
3. Hardcoded debug limits (LIMIT 10 in queries)
4. Legacy WC API still in use
5. No consistent error handling
6. Missing input validation

---

## Phase 1: Code Cleanup (Weeks 1-2)

### Tasks
| ID | Task | Priority | Estimated Effort |
|----|------|----------|------------------|
| 1.1 | Remove duplicate files (keep latest versions) | High | 2 hours |
| 1.2 | Separate MVC components (move GUI out of models) | High | 3 days |
| 1.3 | Standardize naming conventions | Medium | 1 day |
| 1.4 | Remove hardcoded debug limits | Medium | 2 hours |
| 1.5 | Migrate fully to REST API (remove WC API legacy) | High | 2 days |
| 1.6 | Add consistent error handling and logging | High | 2 days |

### Deliverables
- Clean codebase with no duplicates
- MVC separation complete
- All debug code removed or configurable
- Single REST API implementation

---

## Phase 2: Complete Variable Products (Weeks 3-4)

### Tasks
| ID | Task | Priority | Estimated Effort |
|----|------|----------|------------------|
| 2.1 | Review variable product tables (woo_prod_variable_*) | High | 4 hours |
| 2.2 | Complete variable product export logic | High | 3 days |
| 2.3 | Test parent-child product relationships | High | 1 day |
| 2.4 | Handle variation attributes and SKU combinations | High | 2 days |
| 2.5 | Test variable product export end-to-end | High | 1 day |

### Deliverables
- Fully functional variable product export
- Variable products with all variations in WooCommerce
- Documentation of variable product data structure

---

## Phase 3: Order Integration (Weeks 5-6)

### Tasks
| ID | Task | Priority | Estimated Effort |
|----|------|----------|------------------|
| 3.1 | Complete order import from WooCommerce | High | 3 days |
| 3.2 | Map WC order statuses to FA order types | Medium | 1 day |
| 3.3 | Handle order line items, taxes, shipping | High | 2 days |
| 3.4 | Implement order export (FA → WC) if needed | Low | 2 days |
| 3.5 | Test order round-trip | High | 1 day |

### Deliverables
- Orders import correctly from WC to FA
- All order components (line items, taxes, etc.) mapped
- Order status synchronization

---

## Phase 4: Customer & Coupon Sync (Weeks 7-8)

### Tasks
| ID | Task | Priority | Estimated Effort |
|----|------|----------|------------------|
| 4.1 | Complete customer export (FA → WC) | Medium | 2 days |
| 4.2 | Complete customer import (WC → FA) | Medium | 2 days |
| 4.3 | Handle billing/shipping addresses | Medium | 1 day |
| 4.4 | Implement coupon export | Low | 2 days |
| 4.5 | Test customer and coupon workflows | Medium | 1 day |

### Deliverables
- Customer data synchronized between systems
- Coupon export functional
- Address mapping complete

---

## Phase 5: Enhancements & Testing (Weeks 9-10)

### Tasks
| ID | Task | Priority | Estimated Effort |
|----|------|----------|------------------|
| 5.1 | Implement batch processing for large catalogs | Medium | 3 days |
| 5.2 | Add incremental sync (only changed products) | High | 2 days |
| 5.3 | Performance optimization (target >50 products/min) | Medium | 2 days |
| 5.4 | Comprehensive testing (all test cases) | High | 3 days |
| 5.5 | Security review and credential encryption | High | 1 day |

### Deliverables
- Performance meets targets
- All test cases pass
- Security vulnerabilities addressed

---

## Phase 6: Documentation & Deployment (Week 11)

### Tasks
| ID | Task | Priority | Estimated Effort |
|----|------|----------|------------------|
| 6.1 | Update user documentation | Medium | 1 day |
| 6.2 | Create installation guide | High | 4 hours |
| 6.3 | Document API and database schema | Medium | 1 day |
| 6.4 | Create troubleshooting guide | Medium | 4 hours |
| 6.5 | Tag release version | High | 1 hour |

### Deliverables
- Complete documentation set
- Release candidate ready for production

---

## Resource Requirements

### Personnel
- **Developer**: 1 FTE for 11 weeks (or part-time equivalent)
- **Tester**: 0.5 FTE for testing phases
- **Business Analyst**: 0.2 FTE for requirements validation

### Environment
- Development: Local FA + WooCommerce instance
- Testing: Staging environment mirroring production
- Production: Live FA + WooCommerce (not used for development)

### Tools
- Git for version control
- PHP IDE (e.g., PHPStorm, VSCode)
- Postman for API testing
- MySQL client for database inspection

---

## Risks and Mitigation

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| WooCommerce API changes | Medium | High | Use stable API version, monitor WC updates |
| FrontAccounting upgrade breaks module | Medium | High | Test with FA releases, use hooks properly |
| Performance issues with large catalogs | High | Medium | Implement batch/queue processing |
| Data loss during sync | Low | High | Add validation, rollback capability |
| Developer availability | Medium | High | Document code, knowledge transfer |

---

## Success Criteria

### Go-Live Checklist
- [ ] All Phase 1-5 tasks completed
- [ ] All high-priority test cases pass
- [ ] Performance benchmark met (>50 products/minute)
- [ ] Security review completed
- [ ] Documentation complete
- [ ] User acceptance testing passed
- [ ] Rollback plan tested

### Post-Implementation Review (4 weeks after go-live)
- Monitor error logs
- Collect user feedback
- Measure KPIs (see BABOK document)
- Identify enhancement requests
- Plan next release if needed

---

## Timeline Summary

```
Week 1-2:  Phase 1 - Code Cleanup
Week 3-4:  Phase 2 - Variable Products
Week 5-6:  Phase 3 - Order Integration
Week 7-8:  Phase 4 - Customer & Coupon Sync
Week 9-10: Phase 5 - Enhancements & Testing
Week 11:   Phase 6 - Documentation & Deployment
```

**Total Estimated Effort**: ~440 hours (11 weeks × 40 hours)
**Target Completion**: 3 months from project start

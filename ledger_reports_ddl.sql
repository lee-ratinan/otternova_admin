-- =====================================================================================================================
-- Ledger financial reports: entities, base currency, period close, tax, and billing posting (MySQL 8)
-- The same schema as the otternova_api migrations, in the order they run:
--   2026-11-23-000001_CreateOtternovaLedgerEntityTable
--   2026-11-23-000002_CreateOtternovaFxRateTable
--   2026-11-23-000003_AddLedgerEntityAndBaseAmounts
--   2026-11-23-100001_CreateLedgerPeriodTables
--   2026-11-23-200001_AddLedgerTaxCodes
--   2026-11-23-300001_AddLedgerPosting
-- Every table is shown as it ends up after all of them. These migrations are already applied on the local database:
-- this is the record, not something to run there again.
-- =====================================================================================================================


-- ---------------------------------------------------------------------------------------------------------------------
-- New tables
-- ---------------------------------------------------------------------------------------------------------------------

-- A legal entity whose books the ledger keeps; a country can have several (a sole proprietorship, then a company)
CREATE TABLE otternova_ledger_entity
(
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id               CHAR(4) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    entity_name             VARCHAR(255)                                  NOT NULL,
    legal_form              VARCHAR(64)                                   NULL,     -- e.g. Sole proprietorship, Co., Ltd.
    country_code            CHAR(2)                                       NOT NULL, -- ISO 3166-1 alpha-2
    tax_id                  VARCHAR(32)                                   NULL,
    base_currency_code      CHAR(3)                                       NOT NULL, -- ISO 4217; every report is in it
    timezone                VARCHAR(64)                                   NOT NULL, -- IANA, e.g. Asia/Bangkok
    fiscal_year_start_month TINYINT UNSIGNED DEFAULT 1                    NOT NULL, -- 1 = January
    vat_threshold_amount    DECIMAL(19, 4)                                NULL,     -- revenue a year at which VAT registration is due
    vat_registered_on       DATE                                          NULL,
    registered_on           DATE                                          NULL,
    closed_on               DATE                                          NULL,
    closed_through          DATE                                          NULL,     -- last day of the last closed month
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP            NOT NULL,
    updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP            NOT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_otternova_ledger_entity_public_id UNIQUE (public_id),
    CONSTRAINT uq_otternova_ledger_entity_entity_name UNIQUE (entity_name)
)
    COLLATE = utf8mb4_general_ci;

CREATE INDEX country_code ON otternova_ledger_entity (country_code);


-- Exchange rates fetched from the provider (Frankfurter / ECB), kept so a day's rate is fetched once
CREATE TABLE otternova_fx_rate
(
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate_date          DATE                               NOT NULL,
    currency_code      CHAR(3)                            NOT NULL,
    base_currency_code CHAR(3)                            NOT NULL,
    rate               DECIMAL(20, 10)                    NOT NULL, -- base currency per 1 unit of currency_code
    source             VARCHAR(32)                        NOT NULL, -- e.g. frankfurter
    created_at         DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT uq_otternova_fx_rate_day_pair UNIQUE (rate_date, currency_code, base_currency_code)
)
    COLLATE = utf8mb4_general_ci;


-- Each close of a month, and its reopening ('R'), as history
CREATE TABLE otternova_ledger_period
(
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id     CHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    entity_id     INT UNSIGNED                                  NOT NULL,
    period_start  DATE                                          NOT NULL, -- first day of the month
    period_end    DATE                                          NOT NULL, -- last day of the month
    period_status ENUM ('C', 'R') DEFAULT 'C'                   NOT NULL, -- closed, reopened
    closed_by     INT UNSIGNED                                  NULL,
    closed_at     DATETIME                                      NOT NULL,
    reopened_by   INT UNSIGNED                                  NULL,
    reopened_at   DATETIME                                      NULL,
    reopen_reason VARCHAR(255)                                  NULL,
    CONSTRAINT uq_otternova_ledger_period_public_id UNIQUE (public_id),
    CONSTRAINT fk_ledger_period_entity
        FOREIGN KEY (entity_id) REFERENCES otternova_ledger_entity (id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ledger_period_closed_by
        FOREIGN KEY (closed_by) REFERENCES auth_master (id)
            ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_ledger_period_reopened_by
        FOREIGN KEY (reopened_by) REFERENCES auth_master (id)
            ON UPDATE CASCADE ON DELETE SET NULL
)
    COLLATE = utf8mb4_general_ci;

CREATE INDEX entity_id_period_start ON otternova_ledger_period (entity_id, period_start);
CREATE INDEX closed_by ON otternova_ledger_period (closed_by);
CREATE INDEX reopened_by ON otternova_ledger_period (reopened_by);


-- The reports as they were when a month closed. report_type: TB trial balance, BS balance sheet, PL profit and loss
-- (month), PY profit and loss (fiscal year to date), GL general ledger, VT VAT, WT withholding tax,
-- RR revenue register, ER expense register
CREATE TABLE otternova_ledger_report_snapshot
(
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id      INT UNSIGNED                                                      NOT NULL,
    report_type    ENUM ('TB', 'BS', 'PL', 'PY', 'GL', 'VT', 'WT', 'RR', 'ER')       NOT NULL,
    date_from      DATE                                                              NULL, -- NULL: from the first entry
    date_to        DATE                                                              NOT NULL,
    content_json   MEDIUMTEXT                                                        NOT NULL,
    content_sha256 CHAR(64)                                                          NOT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP                                NOT NULL,
    CONSTRAINT uq_otternova_ledger_report_snapshot_period_type UNIQUE (period_id, report_type),
    CONSTRAINT fk_ledger_report_snapshot_period
        FOREIGN KEY (period_id) REFERENCES otternova_ledger_period (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
)
    COLLATE = utf8mb4_general_ci;


-- An entity's VAT and withholding tax codes. tax_kind: VO output VAT, VI input VAT,
-- WD withholding tax deducted from a payee, WS withholding tax deducted by a customer
CREATE TABLE otternova_ledger_tax_code
(
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id      CHAR(4) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    entity_id      INT UNSIGNED                                  NOT NULL,
    tax_code       VARCHAR(16)                                   NOT NULL, -- e.g. VAT7, WHT3
    tax_name       VARCHAR(128)                                  NOT NULL,
    tax_kind       ENUM ('VO', 'VI', 'WD', 'WS')                 NOT NULL,
    rate           DECIMAL(5, 2)                                 NOT NULL, -- percent
    account_id     INT UNSIGNED                                  NULL,     -- the tax account; none for a 0% code
    effective_from DATE                                          NOT NULL,
    effective_to   DATE                                          NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP            NOT NULL,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP            NOT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_otternova_ledger_tax_code_public_id UNIQUE (public_id),
    CONSTRAINT uq_otternova_ledger_tax_code_entity_code UNIQUE (entity_id, tax_code),
    CONSTRAINT fk_ledger_tax_code_entity
        FOREIGN KEY (entity_id) REFERENCES otternova_ledger_entity (id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ledger_tax_code_account
        FOREIGN KEY (account_id) REFERENCES otternova_ledger_account (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
)
    COLLATE = utf8mb4_general_ci;

CREATE INDEX account_id ON otternova_ledger_tax_code (account_id);


-- Which entity books the subscription invoices of a country's businesses from a date on, and into which accounts
CREATE TABLE otternova_ledger_posting_rule
(
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id                   CHAR(4) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    entity_id                   INT UNSIGNED                                  NOT NULL,
    country_code                CHAR(2)                                       NOT NULL, -- of the businesses billed
    effective_from              DATE                                          NOT NULL, -- invoices made before it are never posted
    effective_to                DATE                                          NULL,
    receivable_account_id       INT UNSIGNED                                  NOT NULL,
    deferred_revenue_account_id INT UNSIGNED                                  NOT NULL,
    revenue_account_id          INT UNSIGNED                                  NOT NULL,
    cash_account_id             INT UNSIGNED                                  NOT NULL,
    wht_receivable_account_id   INT UNSIGNED                                  NULL,
    wht_tax_code_id             INT UNSIGNED                                  NULL,
    category_id                 INT UNSIGNED                                  NOT NULL,
    created_at                  DATETIME DEFAULT CURRENT_TIMESTAMP            NOT NULL,
    updated_at                  DATETIME DEFAULT CURRENT_TIMESTAMP            NOT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_otternova_ledger_posting_rule_public_id UNIQUE (public_id),
    CONSTRAINT fk_ledger_posting_rule_entity
        FOREIGN KEY (entity_id) REFERENCES otternova_ledger_entity (id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ledger_posting_rule_receivable
        FOREIGN KEY (receivable_account_id) REFERENCES otternova_ledger_account (id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ledger_posting_rule_deferred_revenue
        FOREIGN KEY (deferred_revenue_account_id) REFERENCES otternova_ledger_account (id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ledger_posting_rule_revenue
        FOREIGN KEY (revenue_account_id) REFERENCES otternova_ledger_account (id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ledger_posting_rule_cash
        FOREIGN KEY (cash_account_id) REFERENCES otternova_ledger_account (id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ledger_posting_rule_wht_receivable
        FOREIGN KEY (wht_receivable_account_id) REFERENCES otternova_ledger_account (id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ledger_posting_rule_wht_tax_code
        FOREIGN KEY (wht_tax_code_id) REFERENCES otternova_ledger_tax_code (id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_ledger_posting_rule_category
        FOREIGN KEY (category_id) REFERENCES otternova_ledger_category (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
)
    COLLATE = utf8mb4_general_ci;

CREATE INDEX country_code_effective_from ON otternova_ledger_posting_rule (country_code, effective_from);
CREATE INDEX entity_id ON otternova_ledger_posting_rule (entity_id);
CREATE INDEX receivable_account_id ON otternova_ledger_posting_rule (receivable_account_id);
CREATE INDEX deferred_revenue_account_id ON otternova_ledger_posting_rule (deferred_revenue_account_id);
CREATE INDEX revenue_account_id ON otternova_ledger_posting_rule (revenue_account_id);
CREATE INDEX cash_account_id ON otternova_ledger_posting_rule (cash_account_id);
CREATE INDEX wht_receivable_account_id ON otternova_ledger_posting_rule (wht_receivable_account_id);
CREATE INDEX wht_tax_code_id ON otternova_ledger_posting_rule (wht_tax_code_id);
CREATE INDEX category_id ON otternova_ledger_posting_rule (category_id);


-- ---------------------------------------------------------------------------------------------------------------------
-- Changes to existing tables
-- ---------------------------------------------------------------------------------------------------------------------

-- Accounts belong to an entity. Added NULL first, filled (see the data section below), then made NOT NULL.
ALTER TABLE otternova_ledger_account
    ADD entity_id INT UNSIGNED NULL AFTER public_id;

-- ... data: the default entity and its accounts, below ...

ALTER TABLE otternova_ledger_account
    MODIFY entity_id INT UNSIGNED NOT NULL,
    ADD KEY entity_id (entity_id),
    ADD CONSTRAINT fk_ledger_account_entity
        FOREIGN KEY (entity_id) REFERENCES otternova_ledger_entity (id)
            ON UPDATE CASCADE;

-- Entries: the amount in the entity's base currency, tax, the transaction they belong to, and the billing event
-- they were posted for (NULL for entries made by hand; such posted rows are read-only)
ALTER TABLE otternova_ledger_master
    ADD base_rate       DECIMAL(20, 10) NULL AFTER billed_amount,  -- base currency per unit of the paid currency
    ADD base_amount     DECIMAL(19, 4)  NULL AFTER base_rate,
    ADD tax_code_id     INT UNSIGNED    NULL AFTER base_amount,
    ADD tax_base_amount DECIMAL(19, 4)  NULL AFTER tax_code_id,    -- in the base currency
    ADD journal_id      CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER tax_base_amount,
    ADD source_key      VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER journal_id, -- e.g. inv:123:paid:1
    ADD KEY tax_code_id (tax_code_id),
    ADD KEY journal_id (journal_id),
    ADD UNIQUE KEY uq_otternova_ledger_master_source_key (source_key),
    ADD CONSTRAINT fk_ledger_master_tax_code
        FOREIGN KEY (tax_code_id) REFERENCES otternova_ledger_tax_code (id)
            ON UPDATE CASCADE;

-- Counterparties: tax identity for the tax reports, and the business one was made for by the billing posting
ALTER TABLE otternova_ledger_counterparty
    ADD tax_id      VARCHAR(32)  NULL AFTER counterparty_description,
    ADD tax_branch  VARCHAR(16)  NULL AFTER tax_id,
    ADD business_id INT UNSIGNED NULL AFTER tax_branch,
    ADD UNIQUE KEY uq_otternova_ledger_counterparty_business (business_id),
    ADD CONSTRAINT fk_ledger_counterparty_business
        FOREIGN KEY (business_id) REFERENCES business_master (id)
            ON UPDATE CASCADE;


-- ---------------------------------------------------------------------------------------------------------------------
-- Data the migrations wrote (once, on the rows already there)
-- ---------------------------------------------------------------------------------------------------------------------

-- 1. When ledger accounts already existed: one default entity, and every account put in it.
--    public_id is a 4-character NanoID (generateNanoId(4)); 'XXXX' stands for the one generated.
INSERT INTO otternova_ledger_entity (public_id, entity_name, country_code, base_currency_code, timezone, fiscal_year_start_month, registered_on)
SELECT 'XXXX', 'OtterNova (Thailand)', 'TH', 'THB', 'Asia/Bangkok', 1, (SELECT MIN(transaction_date) FROM otternova_ledger_master)
WHERE EXISTS (SELECT 1 FROM otternova_ledger_account);

UPDATE otternova_ledger_account
SET entity_id = (SELECT id FROM otternova_ledger_entity WHERE entity_name = 'OtterNova (Thailand)');

-- 2. Base amounts of the entries already there, where no exchange rate is needed: the paid amount when it is in THB,
--    else the billed amount when that is. The rest stay NULL until they are saved again with a rate.
UPDATE otternova_ledger_master
SET base_amount = CASE
                      WHEN transaction_currency_code = 'THB' THEN transaction_amount
                      WHEN billed_currency_code = 'THB' THEN billed_amount
                  END
WHERE transaction_currency_code = 'THB' OR billed_currency_code = 'THB';

-- 3. The VAT registration threshold of the Thai entities in THB: THB 1.8 million a year
UPDATE otternova_ledger_entity
SET vat_threshold_amount = 1800000
WHERE country_code = 'TH' AND base_currency_code = 'THB' AND vat_threshold_amount IS NULL;

-- =====================================================================================================================
-- Staff salary and payslips (MySQL 8): the record of otternova_api migration 2026-11-26-100001_CreateStaffPayrollTables
-- staff_salary        the pay terms of a staff member over time, one row per change (history)
-- staff_payslip       what was actually paid for one pay period, with the totals
-- staff_payslip_line  the earnings, deductions (income tax, social security) and employer contributions of a payslip
-- staff_pay_account  where the pay goes (kept out of staff_master, whose profile any staff member's form can read)
-- Access: the Executive Office (EXE) only, to read and to change, in the Admin app and in the API alike;
-- not HRD or FIN. The salary section of admin/accounts/edit/{uid} shows only for staff accounts.
-- =====================================================================================================================


-- The pay terms of a staff member from effective_date, until the next row's effective_date (end_date is set on the
-- previous row when a new one is added, or when they leave). Never updated in place once a payslip uses it:
-- a raise or a correction is a new row.
CREATE TABLE staff_salary
(
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id            CHAR(8) CHARACTER SET ascii COLLATE ascii_bin  NOT NULL,
    staff_id             INT UNSIGNED                                   NOT NULL,
    entity_id            INT UNSIGNED                                   NULL,     -- the employing otternova_ledger_entity; NULL until one is registered
    effective_date       DATE                                           NOT NULL,
    end_date             DATE                                           NULL,     -- last day; NULL while current
    currency_code        CHAR(3)                                        NOT NULL, -- ISO 4217
    base_amount          DECIMAL(19, 2)                                 NOT NULL, -- per rate_unit
    rate_unit            ENUM ('M', 'W', 'D', 'H')   DEFAULT 'M'        NOT NULL, -- per month, week, day or hour (part-timers, interns)
    pay_frequency        ENUM ('M', 'S', 'W')        DEFAULT 'M'        NOT NULL, -- paid monthly, semi-monthly or weekly
    standard_hours_week  DECIMAL(5, 2)                                  NULL,     -- for pro rata and overtime; NULL = full time
    social_security      TINYINT(1)                  DEFAULT 0          NOT NULL, -- enrolled (Thai SSO etc.); 0 until the entity registers
    change_reason        ENUM ('H', 'P', 'A', 'C', 'O')                 NOT NULL, -- hired, promotion, annual review, correction, other
    note                 VARCHAR(255)                                   NULL,
    created_by           INT UNSIGNED                                   NULL,
    created_at           DATETIME DEFAULT CURRENT_TIMESTAMP             NOT NULL,
    updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP             NOT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_staff_salary_public_id UNIQUE (public_id),
    CONSTRAINT uq_staff_salary_staff_effective UNIQUE (staff_id, effective_date),
    CONSTRAINT fk_staff_salary_staff FOREIGN KEY (staff_id) REFERENCES staff_master (id)
        ON UPDATE CASCADE,                                                         -- RESTRICT on delete: pay history is kept
    CONSTRAINT fk_staff_salary_entity FOREIGN KEY (entity_id) REFERENCES otternova_ledger_entity (id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_staff_salary_created_by FOREIGN KEY (created_by) REFERENCES auth_master (id)
        ON UPDATE CASCADE ON DELETE SET NULL
)
    COLLATE = utf8mb4_general_ci;

CREATE INDEX entity_id ON staff_salary (entity_id);


-- One pay period's pay for one staff member. The person's name, code, title and tax ID are copied in when it is
-- issued, so the payslip still reads the same after they change. Totals are the sums of its lines, kept for lists.
-- Day one (no registered company): one BASE earning line, no deductions, entity_id NULL, net = gross.
CREATE TABLE staff_payslip
(
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id              CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    staff_id               INT UNSIGNED                                   NOT NULL,
    salary_id              INT UNSIGNED                                   NULL,     -- the staff_salary terms it was worked out from
    entity_id              INT UNSIGNED                                   NULL,     -- the paying entity; NULL before registration
    period_start           DATE                                           NOT NULL,
    period_end             DATE                                           NOT NULL,
    pay_date               DATE                                           NOT NULL,
    currency_code          CHAR(3)                                        NOT NULL,
    gross_amount           DECIMAL(19, 2)  DEFAULT 0                      NOT NULL, -- sum of E lines
    deduction_amount       DECIMAL(19, 2)  DEFAULT 0                      NOT NULL, -- sum of D lines
    net_amount             DECIMAL(19, 2)  DEFAULT 0                      NOT NULL, -- gross - deductions: what is transferred
    employer_amount        DECIMAL(19, 2)  DEFAULT 0                      NOT NULL, -- sum of C lines: the employer's cost on top
    status                 ENUM ('D', 'I', 'P', 'V') DEFAULT 'D'          NOT NULL, -- draft, issued, paid, void
    current_flag           TINYINT UNSIGNED DEFAULT 1                     NULL,     -- 1 unless void (NULL): one live payslip a period
    -- snapshot when issued
    employee_code          VARCHAR(32)                                    NULL,
    employee_name          VARCHAR(255)                                   NULL,     -- legal name
    job_title              VARCHAR(128)                                   NULL,
    department_name        VARCHAR(255)                                   NULL,
    tax_id_number          VARCHAR(512)                                   NULL,     -- encrypted, as on staff_master
    bank_name              VARCHAR(128)                                   NULL,
    bank_account_number    VARCHAR(512)                                   NULL,     -- encrypted
    -- payment
    paid_at                DATETIME                                       NULL,
    payment_reference      VARCHAR(128)                                   NULL,     -- the bank transfer's reference
    ledger_id              INT UNSIGNED                                   NULL,     -- the otternova_ledger_master row it posted
    void_reason            VARCHAR(255)                                   NULL,
    note                   VARCHAR(255)                                   NULL,     -- shown on the payslip
    created_by             INT UNSIGNED                                   NULL,
    issued_by              INT UNSIGNED                                   NULL,
    issued_at              DATETIME                                       NULL,
    created_at             DATETIME DEFAULT CURRENT_TIMESTAMP             NOT NULL,
    updated_at             DATETIME DEFAULT CURRENT_TIMESTAMP             NOT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_staff_payslip_public_id UNIQUE (public_id),
    CONSTRAINT uq_staff_payslip_period UNIQUE (staff_id, period_start, period_end, current_flag),
    CONSTRAINT fk_staff_payslip_staff FOREIGN KEY (staff_id) REFERENCES staff_master (id)
        ON UPDATE CASCADE,                                                           -- RESTRICT on delete: payslips are kept
    CONSTRAINT fk_staff_payslip_salary FOREIGN KEY (salary_id) REFERENCES staff_salary (id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_staff_payslip_entity FOREIGN KEY (entity_id) REFERENCES otternova_ledger_entity (id)
        ON UPDATE CASCADE,
    CONSTRAINT fk_staff_payslip_ledger FOREIGN KEY (ledger_id) REFERENCES otternova_ledger_master (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_staff_payslip_created_by FOREIGN KEY (created_by) REFERENCES auth_master (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_staff_payslip_issued_by FOREIGN KEY (issued_by) REFERENCES auth_master (id)
        ON UPDATE CASCADE ON DELETE SET NULL
)
    COLLATE = utf8mb4_general_ci;

CREATE INDEX pay_date ON staff_payslip (pay_date);
CREATE INDEX entity_period ON staff_payslip (entity_id, period_start);
CREATE INDEX status ON staff_payslip (status);


-- The lines of a payslip. line_code is one of the app's codes, so the tax and social security reports can sum
-- them by code: BASE salary, OT overtime, BONUS, ALLOW allowance, COMM commission, ADJ adjustment (E);
-- PIT withheld income tax, SSO social security, PVD provident fund, LOAN, OTHER (D); SSO_ER, PVD_ER (C)
CREATE TABLE staff_payslip_line
(
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payslip_id   INT UNSIGNED                         NOT NULL,
    line_type    ENUM ('E', 'D', 'C')                 NOT NULL, -- earning, deduction, employer contribution
    line_code    VARCHAR(16)                          NOT NULL,
    description  VARCHAR(255)                         NOT NULL, -- as printed, e.g. "Overtime 6 h × 1.5"
    quantity     DECIMAL(10, 2)                       NULL,     -- hours, days
    rate         DECIMAL(19, 4)                       NULL,
    amount       DECIMAL(19, 2)                       NOT NULL, -- always positive; line_type gives the sign
    taxable      TINYINT(1) DEFAULT 1                 NOT NULL, -- counts toward taxable income (E lines)
    sort_order   SMALLINT UNSIGNED DEFAULT 0          NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP   NOT NULL,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP   NOT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_staff_payslip_line_payslip FOREIGN KEY (payslip_id) REFERENCES staff_payslip (id)
        ON UPDATE CASCADE ON DELETE CASCADE                 -- only a draft payslip may be deleted
)
    COLLATE = utf8mb4_general_ci;

CREATE INDEX payslip_type ON staff_payslip_line (payslip_id, line_type);
CREATE INDEX line_code ON staff_payslip_line (line_code);


-- Where the pay goes: current details only (a payslip keeps its own copy). bank_account_number is encrypted.
CREATE TABLE staff_pay_account
(
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id            INT UNSIGNED                       NOT NULL,
    bank_name           VARCHAR(128)                       NULL,
    bank_branch         VARCHAR(128)                       NULL,
    bank_account_name   VARCHAR(255)                       NULL,
    bank_account_number VARCHAR(512)                       NULL, -- encrypted
    updated_by          INT UNSIGNED                       NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_staff_pay_account_staff UNIQUE (staff_id),
    CONSTRAINT fk_staff_pay_account_staff FOREIGN KEY (staff_id) REFERENCES staff_master (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_staff_pay_account_updated_by FOREIGN KEY (updated_by) REFERENCES auth_master (id)
        ON UPDATE CASCADE ON DELETE SET NULL
)
    COLLATE = utf8mb4_general_ci;

<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model for the staff_master table
 */
class StaffMasterModel extends Model
{
    // employment_type
    public const EMPLOYMENT_FULL_TIME = 'F';
    public const EMPLOYMENT_PART_TIME = 'P';
    public const EMPLOYMENT_CONTRACT  = 'C';
    public const EMPLOYMENT_INTERN    = 'I';

    // employment_status
    public const STATUS_ACTIVE     = 'A';
    public const STATUS_PROBATION  = 'P';
    public const STATUS_TERMINATED = 'T';
    public const STATUS_RESIGNED   = 'R';

    // gender
    public const GENDER_MALE          = 'M';
    public const GENDER_FEMALE        = 'F';
    public const GENDER_NON_BINARY    = 'NB';
    public const GENDER_OTHER         = 'X';
    public const GENDER_NOT_SPECIFIED = 'N';

    // national_id_type
    public const ID_NATIONAL_ID  = 'N';
    public const ID_PASSPORT     = 'P';
    public const ID_WORK_PERMIT  = 'W';

    protected $table            = 'staff_master';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'auth_id',
        'department_id',
        'employee_code',
        'job_title',
        'manager_staff_id',
        'employment_type',
        'employment_status',
        'employment_start_date',
        'probation_end_date',
        'employment_end_date',
        'work_country_code',
        'work_email_address',
        'work_telephone_number',
        'legal_first_name',
        'legal_middle_name',
        'legal_last_name',
        'preferred_name',
        'gender',
        'date_of_birth',
        'nationality_code',
        'national_id_type',
        'national_id_number',
        'tax_id_number',
        'address_line_1',
        'address_line_2',
        'address_city',
        'subdivision_code',
        'postal_code',
        'country_code',
        'emergency_contact_name',
        'emergency_contact_relation',
        'emergency_contact_telephone',
        'preferred_language',
        'timezone',
        'avatar_path',
        'status_message',
        'bio',
        'hr_notes',
    ];

    // created_at and updated_at are maintained by the database
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'id'                          => 'permit_empty|is_natural_no_zero',
        'auth_id'                     => 'required|is_natural_no_zero|is_unique[staff_master.auth_id,id,{id}]',
        'department_id'               => 'required|is_natural_no_zero',
        'employee_code'               => 'required|max_length[32]|is_unique[staff_master.employee_code,id,{id}]',
        'job_title'                   => 'required|max_length[128]',
        'manager_staff_id'            => 'permit_empty|is_natural_no_zero|differs[id]',
        'employment_type'             => 'permit_empty|in_list[F,P,C,I]',
        'employment_status'           => 'permit_empty|in_list[A,P,T,R]',
        'employment_start_date'       => 'required|valid_date[Y-m-d]',
        'probation_end_date'          => 'permit_empty|valid_date[Y-m-d]',
        'employment_end_date'         => 'permit_empty|valid_date[Y-m-d]',
        'work_country_code'           => 'required|alpha|exact_length[2]',
        'work_email_address'          => 'permit_empty|valid_email|max_length[128]',
        'work_telephone_number'       => 'permit_empty|max_length[32]',
        'legal_first_name'            => 'required|max_length[128]',
        'legal_middle_name'           => 'permit_empty|max_length[128]',
        'legal_last_name'             => 'required|max_length[128]',
        'preferred_name'              => 'permit_empty|max_length[128]',
        'gender'                      => 'permit_empty|in_list[M,F,NB,X,N]',
        'date_of_birth'               => 'permit_empty|valid_date[Y-m-d]',
        'nationality_code'            => 'permit_empty|alpha|exact_length[2]',
        'national_id_type'            => 'permit_empty|[N,P,W]',
        'national_id_number'          => 'permit_empty|max_length[255]',
        'tax_id_number'               => 'permit_empty|max_length[255]',
        'address_line_1'              => 'permit_empty|max_length[255]',
        'address_line_2'              => 'permit_empty|max_length[255]',
        'address_city'                => 'permit_empty|max_length[128]',
        'subdivision_code'            => 'permit_empty|max_length[8]',
        'postal_code'                 => 'permit_empty|max_length[16]',
        'country_code'                => 'permit_empty|alpha|exact_length[2]',
        'emergency_contact_name'      => 'permit_empty|max_length[255]',
        'emergency_contact_relation'  => 'permit_empty|max_length[64]',
        'emergency_contact_telephone' => 'permit_empty|max_length[32]',
        'preferred_language'          => 'permit_empty|max_length[16]',
        'timezone'                    => 'permit_empty|timezone',
        'avatar_path'                 => 'permit_empty|max_length[255]',
        'status_message'              => 'permit_empty|max_length[255]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * This function returns the configuration of the list view
     * @return array
     */
    public function getListViewConfiguration(): array
    {
        $fields = $this->allowedFields;
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = [
                'data'   => $field,
                'title'  => lang('StaffMaster.fields.' . $field),
                'db'     => 's.' . $field,
            ];
        }
        // Add Badges
        $fieldsWithBadge = [
            'employment_type'   => ['F' => 'primary', 'P' => 'info', 'C' => 'warning', 'I' => 'secondary'],
            'employment_status' => ['A' => 'success', 'P' => 'warning', 'T' => 'danger', 'R' => 'secondary'],
        ];
        foreach ($fieldsWithBadge as $field => $values) {
            $result[$field] = [
                'data'   => $field,
                'title'  => lang('StaffMaster.fields.' . $field),
                'db'     => 's.' . $field,
                'type'   => 'badge',
            ];
            foreach ($values as $value => $class) {
                $result[$field]['badges'][$value] = [
                    'label' => lang('StaffMaster.enum.' . $field . '.' . $value),
                    'class' => 'bg-' . strtolower($class)
                ];
            }
        }
        return $result;
    }

    /**
     * Find a staff record by its auth_master ID
     * @param int $authId
     * @return array|null
     */
    public function findByAuthId(int $authId): ?array
    {
        return $this->where('auth_id', $authId)->first();
    }

    /**
     * Find a staff record by employee code
     * @param string $employeeCode
     * @return array|null
     */
    public function findByEmployeeCode(string $employeeCode): ?array
    {
        return $this->where('employee_code', $employeeCode)->first();
    }

    /**
     * Find all direct reports of a manager
     * @param int $managerStaffId
     * @return array
     */
    public function findDirectReports(int $managerStaffId): array
    {
        return $this->where('manager_staff_id', $managerStaffId)->findAll();
    }
}

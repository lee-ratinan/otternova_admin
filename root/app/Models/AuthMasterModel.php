<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model for the auth_master table
 */
class AuthMasterModel extends Model
{
    // otp_mode
    public const OTP_EMAIL     = 'E';
    public const OTP_TELEPHONE = 'T';
    public const OTP_NONE      = 'X';

    // account_status
    public const STATUS_ACTIVE    = 'A';
    public const STATUS_PENDING   = 'P';
    public const STATUS_BLOCKED   = 'B';
    public const STATUS_SUSPENDED = 'S';

    // account_type
    public const TYPE_STAFF  = 'S';
    public const TYPE_CLIENT = 'C';

    protected $table            = 'auth_master';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'uid',
        'email_address',
        'telephone_number',
        'password_hash',
        'password_expiry',
        'full_name',
        'otp_mode',
        'account_status',
        'account_type',
        'admin_notes',
    ];

    // created_at and updated_at are maintained by the database
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'id'               => 'permit_empty|is_natural_no_zero',
        'uid'              => 'required|max_length[128]|is_unique[auth_master.uid,id,{id}]',
        'email_address'    => 'required|valid_email|max_length[128]|is_unique[auth_master.email_address,id,{id}]',
        'telephone_number' => 'permit_empty|max_length[32]|is_unique[auth_master.telephone_number,id,{id}]',
        'password_expiry'  => 'permit_empty|valid_date[Y-m-d]',
        'full_name'        => 'required|max_length[255]',
        'otp_mode'         => 'permit_empty|in_list[E,T,X]',
        'account_status'   => 'permit_empty|in_list[A,P,B,S]',
        'account_type'     => 'permit_empty|in_list[S,C]',
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
                'title'  => lang('AuthMaster.fields.' . $field),
                'db'     => 'a.' . $field,
            ];
        }
        // Add Badges
        $fieldsWithBadge = [
            'otp_mode'       => ['E' => 'primary', 'T' => 'success', 'X' => 'secondary'],
            'account_status' => ['P' => 'warning', 'A' => 'success', 'B' => 'danger', 'S' => 'secondary'],
            'account_type'   => ['S' => 'success', 'C' => 'info']
        ];
        foreach ($fieldsWithBadge as $field => $values) {
            $result[$field] = [
                'data'   => $field,
                'title'  => lang('AuthMaster.fields.' . $field),
                'db'     => 'a.' . $field,
                'type'   => 'badge',
            ];
            foreach ($values as $value => $class) {
                $result[$field]['badges'][$value] = [
                    'label' => lang('AuthMaster.enum.' . $field . '.' . $value),
                    'class' => 'bg-' . strtolower($class)
                ];
            }
        }
        return $result;
    }

    /**
     * Generate a unique identifier for an email address
     * @param string $email
     * @return string
     */
    public function generateUid(string $email): string
    {
        $email = strtolower($email);
        return preg_replace('/[^a-z0-9]/', '', $email);
    }

    /**
     * @param string $uid
     * @return array|null
     */
    public function findByUid(string $uid): ?array
    {
        return $this->where('uid', $uid)->first();
    }
    /**
     * Find an account by email address
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->where('email_address', $email)->first();
    }

    /**
     * Find an account by telephone number
     * @param string $telephone
     * @return array|null
     */
    public function findByTelephone(string $telephone): ?array
    {
        return $this->where('telephone_number', $telephone)->first();
    }
}

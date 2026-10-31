<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

/**
 * Creates the root admin staff account (App\Database\Seeds\RootAdminSeeder)
 * Depends on auth_master, otternova_department and staff_master.
 */
class SeedRootAdminAccount extends Migration
{
    public function up()
    {
        Database::seeder()->call('RootAdminSeeder');
    }

    public function down()
    {
        // staff_master is removed by the ON DELETE CASCADE on fk_staff_master_auth
        $this->db->table('auth_master')->where('email_address', 'eikin@otternova.com')->delete();
    }
}

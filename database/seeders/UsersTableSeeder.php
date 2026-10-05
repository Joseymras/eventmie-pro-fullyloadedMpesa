<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Classiebit\Eventmie\Models\User;

class UsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        if (!User::count()) {

            $users = array(
                array(
                    'id' => 1,
                    'role_id' => 1,
                    'name' => 'Admin',
                    'email' => 'admin@admin.com',
                    'avatar' => 'users/default.png',
                    'email_verified_at' => '2019-09-02 07:37:28',
                    'password' => bcrypt(Str::random(64)),
                    'remember_token' => NULL,
                    'settings' => '{"locale":"en"}',
                    'created_at' => '2018-12-21 10:25:08',
                    'updated_at' => '2019-09-11 04:28:24',
                    'organisation' => NULL,
                    'stripe_account_id' => '',
                    'seller_name' => '',
                    'seller_info' => '',
                    'seller_tax_info' => '',
                    'seller_signature' => '',
                    'seller_note' => '',
                ),
                array(
                    'id' => 2,
                    'role_id' => 3,
                    'name' => 'Gina White',
                    'email' => 'ginawhite@mail.com',
                    'avatar' => 'users/default.png',
                    'email_verified_at' => '2019-09-02 07:37:28',
                    'password' => bcrypt(Str::random(64)),
                    'remember_token' => NULL,
                    'settings' => '{"locale":"en"}',
                    'created_at' => '2019-09-02 07:37:28',
                    'updated_at' => '2019-09-02 07:37:28',
                    'organisation' => NULL,
                    'stripe_account_id' => '',
                    'seller_name' => '',
                    'seller_info' => '',
                    'seller_tax_info' => '',
                    'seller_signature' => '',
                    'seller_note' => '',
                ),
                array(
                    'id' => 3,
                    'role_id' => 2,
                    'name' => 'David Lane',
                    'email' => 'davidlane@mail.com',
                    'avatar' => 'users/default.png',
                    'email_verified_at' => '2019-09-02 07:37:28',
                    'password' => bcrypt(Str::random(64)),
                    'remember_token' => NULL,
                    'settings' => '{"locale":"en"}',
                    'created_at' => '2019-09-02 07:26:33',
                    'updated_at' => '2019-09-14 08:32:31',
                    'organisation' => NULL,
                    'stripe_account_id' => '',
                    'seller_name' => '',
                    'seller_info' => '',
                    'seller_tax_info' => '',
                    'seller_signature' => '',
                    'seller_note' => '',
                )
            );

            User::insert($users);
            
        } else {

            // if user exist then make first user admin
            $user = User::firstOrNew(['id' => 1]);
            if ($user->exists) {
                $user->fill([
                    'role_id'        => 1,
                ])->save();
            }
        }
        
    }
}
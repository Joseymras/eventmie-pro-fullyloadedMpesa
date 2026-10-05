<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use TCG\Voyager\Models\Setting;

class SettingsTableSeederCustom extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        $setting = $this->findSetting("apps.authorize_login_id");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "Authorize.Net Login Id", "value" => "", "details"=> null, "type" => "text", "order" => "47", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.authorize_transaction_key");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "Authorize.Net Transaction Key", "value" => "", "details"=> null, "type" => "text", "order" => "48", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.authorize_test_mode");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "Authorize.Net Test Mode", "value" => "1", "details" => json_encode([
                "validation" => [
                    "rule" => "in:0,1,on,off"
                ]
            ]), "type" => "checkbox", "order" => "49", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.stripe_public_key");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "Stripe Public Key", "value" => "", "details"=> null, "type" => "text", "order" => "50", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.stripe_secret_key");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "Stripe Secret Key", "value" => "", "details"=> null, "type" => "text", "order" => "51", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.stripe_direct");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "Stripe Direct (Auto-payout to Organizers)", "value" => "0", "details" => json_encode([
                "validation" => [
                    "rule" => "in:0,1,on,off"
                ]
            ]), "type" => "checkbox", "order" => "51", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.bitpay_key_name");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "BitPay Key Name", "value" => "", "details"=> null, "type" => "text", "order" => "54", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.bitpay_encrypt_code");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "BitPay Encrypt Code", "value" => "", "details"=> null, "type" => "text", "order" => "55", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.bitpay_production");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "BitPay Production", "value" => "0", "details" => json_encode([
                "validation" => [
                    "rule" => "in:0,1,on,off"
                ]
            ]), "type" => "checkbox", "order" => "55", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.twilio_sid");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "Twilio Sid", "value" => "", "details"=> null, "type" => "text", "order" => "56", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.twilio_auth_token");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "Twilio Auth Token", "value" => "", "details"=> null, "type" => "text", "order" => "57", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.twilio_number");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "Twilio Number", "value" => "", "details"=> null, "type" => "text", "order" => "58", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.paystack_public_key");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "PayStack Public Key", "value" => "", "details"=> null, "type" => "text", "order" => "59", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.paystack_enabled");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "Enable Paystack", "value" => "0", "details" => json_encode([
                "validation" => [
                    "rule" => "in:0,1,on,off"
                ]
            ]), "type" => "checkbox", "order" => "58", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.paystack_secret_key");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "PayStack Secret Key", "value" => "", "details"=> null, "type" => "text", "order" => "60", "group" => "Apps", ])->save();
        }

        $setting = $this->findSetting("apps.paystack_merchant_email");
        if (!$setting->exists) {
            $setting->fill(["display_name" => "PayStack Merchant Email", "value" => "", "details"=> null, "type" => "text", "order" => "61", "group" => "Apps", ])->save();
        }
        
        
    }

    /**
     * [setting description].
     *
     * @param [type] $key [description]
     *
     * @return [type] [description]
     */
    protected function findSetting($key)
    {
        return Setting::firstOrNew(['key' => $key]);
    }
}
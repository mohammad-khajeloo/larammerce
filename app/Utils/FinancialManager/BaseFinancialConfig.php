<?php

namespace App\Utils\FinancialManager;

use App\Models\Traits\Inputable;
use App\Utils\FinancialManager\Models\BaseModel;
use JsonSerializable;
use Serializable;

abstract class BaseFinancialConfig extends BaseModel implements JsonSerializable, Serializable
{
    use Inputable;


    /**
     * @rules(input_rule="integer")
     * @data(input_type="text")
     */
    public int $tax_percentage;

    /**
     * @rules(input_rule="integer")
     * @data(input_type="text")
     */
    public int $toll_percentage;

    /**
     * @rules(input_rule="bool")
     * @data(input_type="checkbox")
     */
    public bool $is_enabled;

    /**
     * @rules(input_rule="bool")
     * @data(input_type="checkbox")
     */
    public bool $is_manual_stock;

    /**
     * @rules(input_rule="bool")
     * @data(input_type="checkbox")
     */
    public bool $check_exit_tab_sms_notification;

    /**
     * @rules(input_rule="bool")
     * @data(input_type="checkbox")
     */
    public bool $tax_added_to_price;

    /**
     * @rules(input_rule="bool")
     * @data(input_type="checkbox")
     */
    public bool $calc_tax_for_normal_customers;

    /**
     * @rules(input_rule="bool")
     * @data(input_type="checkbox")
     */
    public bool $calc_tax_for_legal_customers;


    public function __construct()
    {
        $this->setDefaults();
    }

    private function setDefaults(){
        $this->is_enabled = false;
        $this->is_manual_stock = false;
        $this->check_exit_tab_sms_notification = true;
        $this->tax_added_to_price = true;
        $this->calc_tax_for_legal_customers = true;
        $this->calc_tax_for_normal_customers = true;
        $this->tax_percentage = "6";
        $this->toll_percentage = "3";
    }

    public function serialize(): bool|string|null
    {
        return json_encode($this);
    }

    public function unserialize(string $data): void
    {
        $this->setDefaults();
        $tmp_data = json_decode($data, true);
        foreach ($tmp_data as $key => $value) {
            $this->$key = $value;
        }
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

}

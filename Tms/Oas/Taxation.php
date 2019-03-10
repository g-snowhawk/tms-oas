<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2020 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Oas;

use DateTime;
use P5\Lang;

/**
 * Category management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Taxation extends \Tms\Oas
{
    /**
     * Using common accessor methods
     */
    use \Tms\Accessor;

    const TEXT_COLOR = [0,66,99];

    protected $filter_items = [];
    protected $operation_filter = [];
    protected $financial_converter = [];

    public function __construct() 
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        if (false !== $operators = $this->db->select(
            'item_code,system_operator',
            'account_items',
            'WHERE userkey = ? AND system_operator IS NOT NULL',
            [$this->uid]
        )) {
            foreach ($operators as $operator) {
                $this->filter_items[$operator['system_operator']] = $operator['item_code'];
                if (in_array($operator['system_operator'], ['BEGINNING_INVENTORY', 'PERIODEND_INVENTORY', 'PURCHASE'])) {
                    $this->operation_filter[] = $operator['item_code'];
                }
            }
        }

        if (false !== $filters = $this->db->select(
            'item_code,transfer_filter',
            'account_items',
            'WHERE userkey = ? AND transfer_filter IS NOT NULL',
            [$this->uid]
        )) {
            foreach ($filters as $filter) {
                if (!isset($this->filter_items[$filter['transfer_filter']])) {
                    $this->filter_items[$filter['transfer_filter']] = [];
                }
                $this->filter_items[$filter['transfer_filter']][] = $filter['item_code'];
            }
        }

        if (false !== $converters = $this->db->select(
            'item_code,financial_converter',
            'account_items',
            'WHERE userkey = ? AND financial_converter IS NOT NULL',
            [$this->uid]
        )) {
            foreach ($converters as $converter) {
                $this->financial_converter[$converter['item_code']] = $converter['financial_converter'];
            }
        }
    }

    protected function transferAmount($year, $item_code, $amount)
    {
        $user = $this->uid;
        // chack date
        if ($year >= date('Y')) {
            return true;
        }
        if ($amount === 0) {
            return true;
        }

        $start = sprintf("%d-01-01 00:00:00", $year + 1);
        $end   = sprintf("%d-01-01 23:59:59", $year + 1);
        $where =  "userkey = ?
                   AND category = ?
                   AND line_number = ?
                   AND (issue_date >= ? AND issue_date <= ?)";
        $replaces = [$user, 'A', (int)$item_code, $start, $end];

        $sql = "SELECT COUNT(page_number) AS cnt
                  FROM `table::transfer`
                 WHERE $where";
        $count = 0;
        if ($this->db->query($sql, $replaces)) {
            $count = $this->db->fetchColumn(0);
        }
        $amount_left  = ($amount < 0) ? abs($amount) : null;
        $amount_right = ($amount < 0) ? null : abs($amount);
        $code_left  = ($amount < 0) ? $item_code : null;
        $code_right = ($amount < 0) ? null : $item_code;
        if ($count > 0) {
            $data = [
                'item_code_left' => $code_left,
                'amount_left' => $amount_left,
                'summary' => Lang::translate('FROM_PRIV_YEAR'),
                'amount_right' => $amount_right,
                'item_code_right' => $code_right,
            ];
            return $this->db->update('transfer', $data, $where, $replaces);
        } 
        $data = [
            'issue_date' => $start,
            'page_number' => 1,
            'userkey' => $user,
            'category' => 'A',
            'line_number' => (int)$item_code,
            'item_code_left' => $code_left,
            'amount_left' => $amount_left,
            'summary' => Lang::translate('FROM_PRIV_YEAR'),
            'amount_right' => $amount_right,
            'item_code_right' => $code_right,
        ];
        return $this->db->insert('transfer', $data);
    }
}

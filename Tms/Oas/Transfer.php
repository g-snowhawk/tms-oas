<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2019 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Oas;

use DateTime;

/**
 * Category management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Transfer extends \Tms\Oas
{
    /**
     * Using common accessor methods
     */
    use \Tms\Accessor;

    const LINE_COUNT = ['T' => 7, 'P' => 4, 'R' => 4];
    const TRANSFER_TABLE = 'transfer';
    const ACCOUNT_ITEM_TABLE = 'account_items';
    const ITEMS_BY_LINE = [
        'amount_left','item_code_left',
        'summary',
        'item_code_right','amount_right',
        'note'
    ];

    /**
     * Save the data.
     *
     * @return bool
     */
    protected function save()
    {
        $nocommit = (func_num_args() > 0) ? func_get_arg(0) : false;

        $skip = ['userkey','modify_date'];

        $post = $this->request->post();

        $privilege_type = (empty($post['id'])) ? 'create' : 'update';
        $this->checkPermission('oas.transfer.'.$privilege_type);

        $verification_recipe = [
            ['vl_categoty', 'category', 'empty'],
            ['vl_summary', 'summary', 'empty'],
        ];

        if (!$this->validate($verification_recipe)) {
            return false;
        }

        if (true !== $nocommit) {
            $this->db->begin();
        }

        $label_cash = \P5\Lang::translate('LABEL_CASH');
        $item_code_of_cash = $this->db->get(
            'item_code', self::ACCOUNT_ITEM_TABLE, 
            'userkey = ? AND item_name = ?',
            [$this->uid, $label_cash]
        );

        $table_columns = $this->db->getFields($this->db->TABLE(self::TRANSFER_TABLE));

        $sql_function = 'update';
        $page_number = (int)$post['page_number'];
        if (empty($page_number)) {
            $page_number = $this->newTransferNumber($post['issue_date'], $post['category']);
            if ($page_number < 0) {
                return false;
            }

            if (false === $this->shiftTransferNumber($post['issue_date'], $page_number, $post['category'], 1)) {
                return false;
            }
            $sql_function = 'insert';
        }

        $save = [
            'issue_date' => $post['issue_date'],
            'userkey' => $this->uid,
            'category' => $post['category'],
            'page_number' => $page_number,
        ];

        if (isset($post['trade'])) {
            $save['trade'] = $post['trade'];
        }

        $statement = 'userkey = ?'
            . ' AND issue_date = ?'
            . ' AND page_number = ?'
            . ' AND line_number = ?'
            . ' AND category = ?';
        foreach (range(1, self::LINE_COUNT[$post['category']]) as $line_number) {
            if (empty($post['amount_left'][$line_number])
                 && empty($post['item_code_left'][$line_number])
                 && empty($post['summary'][$line_number])
                 && empty($post['amount_right'][$line_number])
                 && empty($post['item_code_right'][$line_number])
            ) {
                if ($sql_function === 'update') {
                    $options = [$this->uid, $post['issue_date'], $page_number, $line_number, $post['category']];
                    if ('1' === $this->db->get('locked', self::TRANSFER_TABLE, $statement, $options)) {
                        return false;
                    }
                    if (false === ($result = $this->db->delete(
                        self::TRANSFER_TABLE, $statement, $options)
                    )) {
                        break;
                    }
                }
                continue;
            }

            // The item transfered by cash
            if ($post['category'] === 'P' && !empty($post['amount_left'][$line_number])) {
                $post['amount_right'][$line_number] = $post['amount_left'][$line_number];
                $post['item_code_right'][$line_number] = $item_code_of_cash;
            } elseif ($post['category'] === 'R' && !empty($post['amount_right'][$line_number])) {
                $post['amount_left'][$line_number] = $post['amount_right'][$line_number];
                $post['item_code_left'][$line_number] = $item_code_of_cash;
            }

            $save['line_number'] = $line_number;
            foreach (self::ITEMS_BY_LINE as $key) {
                $save[$key] = (!empty($post[$key][$line_number]))
                    ? $post[$key][$line_number] : null;
            }

            if ($sql_function === 'insert'
                || (
                    $sql_function === 'update'
                    && false === $this->db->exists(
                        self::TRANSFER_TABLE,
                        $statement,
                        [$this->uid, $post['issue_date'], $page_number, $line_number, $post['category']]
                    )
                )
            ) {
                $result = $this->db->insert(self::TRANSFER_TABLE, $save);
            } else {
                $result = $this->db->update(
                    self::TRANSFER_TABLE, $save,
                    $statement,
                    [$this->uid, $post['issue_date'], $page_number, $line_number, $post['category']]
                );
            }

            if ($result === false) {
                break;
            }
        }

        if ($result !== false) {
            //$this->app->logger->log("Save the transfer", 201);

            $this->saved_issue_date = $save['issue_date'];
            $this->saved_page_number = $page_number;

            return (true !== $nocommit) ? $this->db->commit() : true;
        }
        if (true !== $nocommit) {
            trigger_error($this->db->error());
            $this->db->rollback();
        }

        return false;
    }

    /**
     * Remove data.
     *
     * @return bool
     */
    protected function remove()
    {
    }

    private function newTransferNumber($issue_date, $category) : int
    {
        if (empty($category)) {
            return -1;
        }

        $timestamp = strtotime($issue_date);

        $statement = 'userkey = ? AND category = ? AND issue_date <= ?';
        $options = [$this->uid, $category, $issue_date];

        $options_count = count($options);

        switch ($this->app->cnf('oas:reset_transfer_number_type')) {
            case 'fiscal_year' :
                $year = date('Y', $timestamp);
                $start_fiscal_year = $this->app->cnf('oas:start_fiscal_year');
                if (empty($start_fiscal_year)) {
                    $start_fiscal_year = '04-01';
                }

                // TODO: $start_fiscal_year should be checked with valid date formats
                // else {
                //     ...
                // }

                $issue_date = "$year-$start_fiscal_year";
                $options[] = date('Y-m-d', strtotime($issue_date));
                break;
            case 'year' :
                $options[] = date('Y-01-01', $timestamp);
                break;
            case 'month' :
                $options[] = date('Y-m-01', $timestamp);
                break;
        }
        if (count($options) > $options_count) {
            $statement .= ' AND issue_date >= ?';
        }

        $latest_number = $this->db->get(
            'page_number',
            self::TRANSFER_TABLE,
            $statement . ' ORDER BY issue_date DESC LIMIT 1',
            $options
        );

        return (int)$latest_number + 1;
    }

    protected function shiftTransferNumber($issue_date, $page_number, $category, $value)
    {
        if ($value === -1) {
            //
        }

        $date_time = new DateTime($issue_date);

        $statement = 'userkey = ? AND category = ? AND page_number >= ? AND locked <> ?';
        $options = [$this->uid, $category, $page_number, '1'];

        $options_count = count($options);

        switch ($this->app->cnf('oas:reset_transfer_number_type')) {
            case 'fiscal_year' :
                $year = $date_time->format('Y');
                $start_fiscal_year = $this->app->cnf('oas:start_fiscal_year');
                if (empty($start_fiscal_year)) {
                    $start_fiscal_year = '04-01';
                }

                // TODO: $start_fiscal_year should be checked with valid date formats
                // else {
                //     ...
                // }

                $start = new DateTime("$year-$start_fiscal_year");
                $options[] = $start->format('Y-m-d');
                $end = new DateTime($start->modify('+1 year')->format('Y-m-d'));
                $options[] = $end->modify('-1 day')->format('Y-m-d');
                break;
            case 'year' :
                $options[] = $date_time->format('Y-01-01');
                $options[] = $date_time->format('Y-12-31');
                break;
            case 'month' :
                $month = $date_time->format('Y-m');
                foreach (['first','last'] as $at) {
                    $day = new DateTime("$at day of $month");
                    $options[] = $day->format('Y-m-d');
                }
                break;
        }
        if (count($options) > $options_count) {
            $statement .= ' AND issue_date >= ? AND issue_date <= ?';
        }

        return $this->db->update(
            'transfer', [], 
            $statement . ' ORDER BY page_number DESC', $options,
            ['page_number' => "page_number + '$value'"]
        );
    }

    protected function latestTransfer($category, $columns = '*')
    {
        return $this->db->get(
            $columns,
            self::TRANSFER_TABLE,
            'userkey = ? AND category = ? ORDER BY issue_date DESC, page_number DESC LIMIT 1',
            [$this->uid, $category]
        );
    }

    protected function firstTransfer($category, $columns = '*')
    {
        return $this->db->get(
            $columns,
            self::TRANSFER_TABLE,
            'userkey = ? AND category = ? ORDER BY issue_date ASC, page_number ASC LIMIT 1',
            [$this->uid, $category]
        );
    }

    protected function previousTransfer($current_issue_date, $current_page_number, $category, $columns = '*')
    {
        $sql = "SELECT $columns
                  FROM (SELECT *, CONCAT(issue_date,'-',page_number) AS sorter FROM table::" . self::TRANSFER_TABLE . " WHERE userkey = ? AND category = ?) AS transfer
                 WHERE sorter < ? GROUP BY sorter ORDER BY sorter DESC LIMIT 1";

        $prepared_statement = $this->db->prepare($sql);
        $this->db->execute([$this->uid, $category, "$current_issue_date-$current_page_number"]);

        return $this->db->fetch();
    }

    protected function nextTransfer($current_issue_date, $current_page_number, $category, $columns = '*')
    {
        $sql = "SELECT $columns
                  FROM (SELECT *, CONCAT(issue_date,'-',page_number) AS sorter FROM table::" . self::TRANSFER_TABLE . " WHERE userkey = ? AND category = ?) AS transfer
                 WHERE sorter > ? GROUP BY sorter ORDER BY sorter ASC LIMIT 1";

        $prepared_statement = $this->db->prepare($sql);
        $this->db->execute([$this->uid, $category, "$current_issue_date-$current_page_number"]);

        return $this->db->fetch();
    }

    protected function currentTransfer($issue_date, $page_number, $category, $columns = '*')
    {
        $issue_date = date('Y-m-d', strtotime($issue_date));
        return $this->db->select(
            '*', self::TRANSFER_TABLE,
            'WHERE userkey = ? AND issue_date = ? AND page_number = ? AND category = ?',
            [$this->uid, $issue_date, $page_number, $category]
        );
    }

    protected function transferByDay($issue_date, $category, $columns = '*', $page_order = 'DESC')
    {
        $issue_date = date('Y-m-d', strtotime($issue_date));
        return $this->db->get(
            $columns,
            self::TRANSFER_TABLE,
            "userkey = ? AND issue_date = ? AND category = ? ORDER BY page_number $page_order LIMIT 1",
            [$this->uid, $issue_date, $category]
        );
    }
}

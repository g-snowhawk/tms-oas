<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2020 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Oas\Taxation;

use DateTime;
use P5\Http;
use P5\Lang;
use Tms\Pdf;

/**
 * Category management response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Socialinsurance extends \Tms\Oas\Taxation
{
    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->view->bind(
            'header',
            ['title' => Lang::translate('HEADER_TITLE'), 'id' => 'osa-taxation-socialinsurance', 'class' => 'taxation']
        );
    }

    /**
     * Default view.
     */
    public function defaultView() : void
    {
        $this->checkPermission('oas.taxation.read');

        $template_path = 'oas/taxation/social_insurance.tpl';
        $html_id = 'oas-taxation-socialinsurance';

        $sql = "SELECT year
                  FROM table::account_book
                 WHERE userkey = ? AND locked = '0'
                 GROUP BY year
                 ORDER BY year";

        $years = [];
        if (false !== $this->db->query($sql, [$this->uid])) {
            while($unit = $this->db->fetch()) {
                $years[] = $unit['year'];
            }
        } else {
            echo $this->db->error();
        }
        $years[] = date('Y');
        $years = array_unique($years);
        $this->view->bind('years', $years);

        $year = $this->session->param('si_year');
        if (empty($year)) {
            $year = $years[0];
        }

        $post = $this->request->param();
        if (!empty($post['year'])) {
            $year = $post['year'];
        }
        $this->view->bind('post', $post);

        $list = $this->db->select('id,year,colnumber,title,amount', 'social_insurance', 'WHERE year = ? AND userkey = ? ORDER BY colnumber', [$year, $this->uid]);
        foreach ($list as &$unit) {
            $unit['json'] = json_encode([
                'year' => $unit['year'],
                'colnumber' => $unit['colnumber'],
                'title' => $unit['title'],
                'amount' => $unit['amount'],
            ], JSON_UNESCAPED_UNICODE);
        }
        unset($unit);
        $this->view->bind('list', $list);

        $this->session->clear('si_year');

        $this->setHtmlId($html_id);
        $this->view->render($template_path);
    }

    public function save(): void
    {
        $data = $this->createSaveData(
            'social_insurance',
            $this->request->param(),
            ['id','userkey','modify_date']
        );
        $data['userkey'] = $this->uid;

        $this->db->begin();
        if (false !== $this->db->merge(
            'social_insurance',
            $data,
            ['id','userkey','modify_date'],
            'social_insurance_uk_1'
        )) {
            $year = $data['year'];
            $sql = "SELECT SUM(amount) AS amount,
                            MIN(LEFT(colnumber, 2)) AS colnumber
                      FROM table::social_insurance
                     WHERE year = ? AND userkey = ?
                     GROUP BY colnumber";
            if (false !== $this->db->query($sql, [$year, $this->uid])) {
                $data = [];
                while ($unit = $this->db->fetch()) {
                    $key = 'col_' . $unit['colnumber'];
                    $data[$key] = $unit['amount'];
                }
                if (false !== $this->updateAccountBook($year, $data)) {
                    $this->db->commit();

                    $this->session->param('si_year', $this->request->param('year'));

                    $url = $this->app->systemURI().'?mode=oas.taxation.socialinsurance';
                    Http::redirect($url);
                }
            }
        }
        trigger_error($this->db->error());
        $this->db->rollback();
        $this->defaultView();
    }

    public function remove()
    {
        $id = $this->request->param('id');
        $table = 'social_insurance';
        $statement = 'userkey = ? AND id = ?';
        $replaces = [$this->uid, $id];
        $year = $this->db->get('year', $table, $statement, $replaces);

        $this->db->begin();
        if ($this->db->delete($table, $statement, $replaces)) {
            $this->db->commit();

            $this->session->param('si_year', $year);

            $url = $this->app->systemURI().'?mode=oas.taxation.socialinsurance';
            Http::redirect($url);
        }
        trigger_error($this->db->error());
        $this->db->rollback();
        $this->defaultView();
    }
}

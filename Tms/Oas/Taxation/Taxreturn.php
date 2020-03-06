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

use P5\Lang;
use Tms\Pdf;

/**
 * Category management response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Taxreturn extends \Tms\Oas\Taxation
{
    const LINE_STYLE = [
        'width' => 0.3,
        'cap'   => 'round',
        'join'  => 'round',
        'dash'  => 0,
        'color' => [0, 66, 99]
    ];

    const PDF_ELLIPSE_MAP = [
        'gender' => [
            1 => 103.1,
            2 => 108.0,
        ],
        'formtype' => [
            1 => 93.0,
        ],
        'phoneplace' => [
            1 => 161.5,
            2 => 170.0,
            3 => 179.0,
        ],
        'bank_type' => [
            1 => ['x' => 147.8, 'y' => 241.0],
            2 => ['x' => 147.8, 'y' => 243.4],
            3 => ['x' => 153.2, 'y' => 243.4],
            4 => ['x' => 147.8, 'y' => 245.6],
            5 => ['x' => 153.2, 'y' => 245.6],
        ],
        'branch_type' => [
            1 => ['x' => 186.8, 'y' => 241.0],
            2 => ['x' => 192.3, 'y' => 241.0],
            3 => ['x' => 187.8, 'y' => 243.4],
            4 => ['x' => 186.8, 'y' => 245.6],
            5 => ['x' => 192.3, 'y' => 245.6],
        ],
        'account_type' => [
            1 => 161.2,
            2 => 168.8,
            3 => 176.3,
            4 => 183.8,
            5 => 191.4,
        ],
    ];

    private $pension = 0;
    private $mutualaid = 0;
    private $lifeinsurance = 0;

    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->view->bind(
            'header',
            [
                'title' => Lang::translate('HEADER_TITLE'),
                'id' => 'osa-taxation-taxreturn',
                'class' => 'taxation'
            ]
        );

        $paths = $this->view->getPaths();
        $this->pdf = new Pdf($paths);
    }

    /**
     * Default view.
     */
    public function defaultView() : void
    {
        $this->checkPermission('oas.taxation.read');

        $template_path = 'oas/taxation/tax_return.tpl';
        $html_id = 'oas-taxation-taxreturn';

        $form_params = $this->view->param('form');
        if (is_null($form_params)) {
            $form_params = [];
        }
        $form_params['target'] = 'TmsPDFWindow';
        $this->view->bind('form', $form_params);

        $this->setHtmlId($html_id);
        $this->view->render($template_path);
    }

    public function pdf() : void
    {
        $target_year = $this->request->POST('nendo') . '-01-01';
        $year = date('Y', strtotime($target_year));

        if ($year === date('Y')) {
            trigger_error('Today is still in the period.', E_USER_ERROR);
        }

        $this->pdf->loadTemplate("oas/taxation/tax_return_B.pdf");

        $this->page1($target_year);
        $this->page2($target_year);

        $year = date('Y', strtotime($target_year));
        $file = $this->getPdfPath($year, 'taxation', 'bluepaper.pdf');
        $locked = ($this->request->POST('locked') === '1') ? true : false;
        $this->outputPdf(basename($file), dirname($file), true, $locked);
    }

    private function page1($target_year)
    {
        $tplIdx = $this->pdf->addPageFromTemplate(1); 

        $this->drawHeader($target_year);
        $this->drawBank();
        $this->drawDetail($target_year);
    }

    private function page2($target_year)
    {
        $tplIdx = $this->pdf->addPageFromTemplate(2); 
        // 
        $data = [];
        $data['address1'] = $this->userinfo['city'] . $this->userinfo['town'] . $this->userinfo['address1'];
        $data['company']  = $this->userinfo['company'];
        $data['name']     = $this->userinfo['fullname'];
        $data['rubi']     = $this->userinfo['fullname_rubi'];
        $data['nengo'] = $this->toWareki($target_year);

        $data['kokumin'] = Lang::translate('KOKUMINNENKIN');
        $data['shokibo'] = Lang::translate('SHOKIBOKYOSAI');
        $data['nenkin']  = number_format($this->pension);
        $data['syaho']   = number_format($this->pension);
        $data['kyosai']  = number_format($this->mutualaid);
        $data['kakekin'] = number_format($this->mutualaid);
        $data['seimei']  = number_format($this->lifeinsurance);

        $ary = [
            ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'address1', 'suffix' => '', 'x' =>    34, 'y' =>   44, 'type' => 'Cell', 'width' => 68,   'height' =>    8, 'align' => 'L', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'company',  'suffix' => '', 'x' =>    34, 'y' =>   48, 'type' => 'Cell', 'width' => 68,   'height' =>    8, 'align' => 'L', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' =>  7, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'rubi',     'suffix' => '', 'x' =>    34, 'y' =>   54, 'type' => 'Cell', 'width' => 66,   'height' =>    6, 'align' => 'L', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' => 11, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'name',     'suffix' => '', 'x' =>    34, 'y' => 55.5, 'type' => 'Cell', 'width' => 66,   'height' =>   10, 'align' => 'L', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 11, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'nengo',    'suffix' => '', 'x' =>  28.0, 'y' =>   13, 'type' => 'Cell', 'width' => 10,   'height' =>  6.2, 'align' => 'R', 'flg' => true, 'pitch' => 2.9],
            ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'kokumin',  'suffix' => '', 'x' =>   117, 'y' =>   26, 'type' => 'Cell', 'width' => 17.2, 'height' =>  6.9, 'align' => 'L', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'nenkin',   'suffix' => '', 'x' =>   134, 'y' =>   26, 'type' => 'Cell', 'width' => 19.6, 'height' =>  6.9, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'syaho',    'suffix' => '', 'x' =>   134, 'y' =>   47, 'type' => 'Cell', 'width' => 19.6, 'height' =>  6.9, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'seimei',   'suffix' => '', 'x' =>   134, 'y' =>   54, 'type' => 'Cell', 'width' => 19.6, 'height' =>  6.1, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'shokibo',  'suffix' => '', 'x' => 162.5, 'y' =>   26, 'type' => 'Cell', 'width' => 17.2, 'height' =>  6.9, 'align' => 'L', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'kyosai',   'suffix' => '', 'x' =>   180, 'y' =>   26, 'type' => 'Cell', 'width' => 19.6, 'height' =>  6.9, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'kakekin',  'suffix' => '', 'x' =>   180, 'y' =>   47, 'type' => 'Cell', 'width' => 19.6, 'height' =>  6.9, 'align' => 'R', 'flg' => true],
        ];
        $this->pdf->draw($ary, $data);
    }

    public function drawHeader($target_year)
    {
        $data = [];
        $data['address1'] = $this->userinfo['city'] . $this->userinfo['town'] . $this->userinfo['address1'];
        $data['address2'] = $this->userinfo['address2'];
        $data['company']  = $this->userinfo['company'];
        $data['name']     = $this->userinfo['fullname'];
        $data['rubi']     = mb_convert_kana($this->userinfo['fullname_rubi'], 'k', 'utf-8');
        $tel = explode("-", $this->userinfo['tel']);
        $data['tel1']     = $tel[0];
        $data['tel2']     = $tel[1];
        $data['tel3']     = $tel[2];
        $zip = explode("-", $this->userinfo['zip']);
        $data['zip1']     = $zip[0];
        $data['zip2']     = $zip[1];

        // fixed properties
        if (!is_null($this->oas_config)) {
            $data['caddress'] = $this->oas_config->caddress;
            $data['works']    = $this->oas_config->works;
            $data['nushi']    = $this->oas_config->head_of_household;
            $data['gara']     = $this->oas_config->relationship;
            $data['gengo']    = $this->oas_config->gengo;
            $data['bYear']    = $this->oas_config->birth_year;
            $data['bMonth']   = $this->oas_config->birth_month;
            $data['bDay']     = $this->oas_config->birth_day;
            $data['kankatu']  = $this->oas_config->jurisdiction;
            $data['kubun']    = $this->oas_config->declaration_type;
        }

        // today
        $data['year']  = $this->toWareki(date('Y-m-d'));
        $data['month'] = date('n');
        $data['day']   = date('j');
        $data['nengo'] = $this->toWareki($target_year);;

        $lh =  (empty($data['address2'])) ? 9.2 : 4.5;
        $ary = [
            ['font' => $this->mono,   'style' => '',  'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'zip1',     'suffix' => '', 'x' =>  33.3, 'y' =>   21, 'type' => 'Cell', 'width' => 14.3, 'height' => 5.5, 'align' => 'C', 'flg' => true, 'pitch' => 3.3],
            ['font' => $this->mono,   'style' => '',  'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'zip2',     'suffix' => '', 'x' =>  51.0, 'y' =>   21, 'type' => 'Cell', 'width' => 19.2, 'height' => 5.5, 'align' => 'C', 'flg' => true, 'pitch' => 3.3],
            ['font' => $this->mincho, 'style' => '',  'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'address1', 'suffix' => '', 'x' =>  30.5, 'y' => 29.0, 'type' => 'Cell', 'width' => 68.5, 'height' => 8.8, 'align' => 'L', 'flg' => true],
            ['font' => $this->mincho, 'style' => '',  'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'caddress', 'suffix' => '', 'x' =>  30.5, 'y' => 49.5, 'type' => 'Cell', 'width' =>   56, 'height' =>   8, 'align' => 'L', 'flg' => true],
            ['font' => $this->mincho, 'style' => '',  'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'rubi',     'suffix' => '', 'x' => 111.5, 'y' => 26.5, 'type' => 'Cell', 'width' =>   68, 'height' =>   7, 'align' => 'L', 'flg' => true, 'pitch' => 3.42],
            ['font' => $this->mincho, 'style' => '',  'size' => 11, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'name',     'suffix' => '', 'x' =>   113, 'y' => 34.5, 'type' => 'Cell', 'width' =>   69, 'height' => 8.3, 'align' => 'L', 'flg' => true],
            ['font' => $this->mincho, 'style' => '',  'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'works',    'suffix' => '', 'x' =>   111, 'y' => 45.0, 'type' => 'Cell', 'width' =>   23, 'height' => 6.3, 'align' => 'C', 'flg' => true],
            ['font' => $this->mincho, 'style' => '',  'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'company',  'suffix' => '', 'x' =>   135, 'y' => 45.0, 'type' => 'Cell', 'width' =>   23, 'height' => 6.3, 'align' => 'C', 'flg' => true],
            ['font' => $this->mincho, 'style' => '',  'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'nushi',    'suffix' => '', 'x' =>   159, 'y' => 45.0, 'type' => 'Cell', 'width' =>   21, 'height' => 6.3, 'align' => 'C', 'flg' => true],
            ['font' => $this->mincho, 'style' => '',  'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'gara',     'suffix' => '', 'x' =>   180, 'y' => 45.0, 'type' => 'Cell', 'width' => 14.6, 'height' => 6.3, 'align' => 'C', 'flg' => true],
            ['font' => $this->mono,   'style' => '',  'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'gengo',    'suffix' => '', 'x' => 108.5, 'y' => 50.2, 'type' => 'Cell', 'width' =>  4.5, 'height' => 6.7, 'align' => 'R', 'flg' => true],
            ['font' => $this->mono,   'style' => '',  'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'bYear',    'suffix' => '', 'x' => 116.0, 'y' => 50.2, 'type' => 'Cell', 'width' =>  9.4, 'height' => 6.7, 'align' => 'R', 'flg' => true, 'pitch' => 3.3],
            ['font' => $this->mono,   'style' => '',  'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'bMonth',   'suffix' => '', 'x' => 128.5, 'y' => 50.2, 'type' => 'Cell', 'width' =>  9.4, 'height' => 6.7, 'align' => 'R', 'flg' => true, 'pitch' => 3.3],
            ['font' => $this->mono,   'style' => '',  'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'bDay',     'suffix' => '', 'x' => 141.0, 'y' => 50.2, 'type' => 'Cell', 'width' =>  9.4, 'height' => 6.7, 'align' => 'R', 'flg' => true, 'pitch' => 3.3],
            ['font' => $this->gothic, 'style' => '',  'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'tel1',     'suffix' => '', 'x' => 158.5, 'y' =>   52, 'type' => 'Cell', 'width' =>   10, 'height' => 5.3, 'align' => 'C', 'flg' => true],
            ['font' => $this->gothic, 'style' => '',  'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'tel2',     'suffix' => '', 'x' => 170.7, 'y' =>   52, 'type' => 'Cell', 'width' =>  9.8, 'height' => 5.3, 'align' => 'C', 'flg' => true],
            ['font' => $this->gothic, 'style' => '',  'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'tel3',     'suffix' => '', 'x' => 182.0, 'y' =>   52, 'type' => 'Cell', 'width' => 11.6, 'height' => 5.3, 'align' => 'C', 'flg' => true],
            ['font' => $this->gothic, 'style' => '',  'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'nengo',    'suffix' => '', 'x' =>  63.0, 'y' => 13.5, 'type' => 'Cell', 'width' =>   10, 'height' => 6.2, 'align' => 'R', 'flg' => true, 'pitch' => 3.0],
            ['font' => $this->mincho, 'style' => '',  'size' =>  6, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'year',     'suffix' => '', 'x' =>  22.2, 'y' => 50.3, 'type' => 'Cell', 'width' =>    5, 'height' => 2.8, 'align' => 'C', 'flg' => true],
            ['font' => $this->mincho, 'style' => '',  'size' =>  7, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'year',     'suffix' => '', 'x' =>  20.0, 'y' => 15.0, 'type' => 'Cell', 'width' =>  7.7, 'height' => 3.8, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '',  'size' =>  7, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'month',    'suffix' => '', 'x' =>  29.0, 'y' => 15.0, 'type' => 'Cell', 'width' =>  7.7, 'height' => 3.8, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '',  'size' =>  7, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'day',      'suffix' => '', 'x' =>  37.5, 'y' => 15.0, 'type' => 'Cell', 'width' =>  7.7, 'height' => 3.8, 'align' => 'R', 'flg' => true],
            ['font' => $this->mincho, 'style' => '',  'size' =>  8, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'kankatu',  'suffix' => '', 'x' =>  17.0, 'y' =>   11, 'type' => 'Cell', 'width' => 19.4, 'height' => 3.8, 'align' => 'C', 'flg' => true],
            ['font' => $this->gothic, 'style' => 'B', 'size' => 17, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'kubun',    'suffix' => '', 'x' => 122.0, 'y' => 11.7, 'type' => 'Cell', 'width' =>   20, 'height' => 9.0, 'align' => 'L', 'flg' => true],
        ];
        $this->pdf->draw($ary, $data);

        $x1 = self::PDF_ELLIPSE_MAP['gender'][$this->oas_config->gender];
        $x2 = self::PDF_ELLIPSE_MAP['formtype'][1];
        $x3 = self::PDF_ELLIPSE_MAP['phoneplace'][$this->oas_config->phoneplace];
        $line_map = [
            ['name' => 'circle',  'x' => $x1, 'y' =>   49, 'r' => 1.6, 'astart' => 0, 'angend' => 360, 'type' => 'Circle', 'style' => 'D', 'line_style' => self::LINE_STYLE],
            ['name' => 'circle',  'x' => $x2, 'y' =>   60, 'r' => 2,   'astart' => 0, 'angend' => 360, 'type' => 'Circle', 'style' => 'D', 'line_style' => self::LINE_STYLE],
            ['name' => 'ellipse', 'x' => $x3, 'y' => 51.8, 'rx' => 4, 'ry' => 1.6, 'angle' => 0, 'astart' => 0, 'afinish' => 360, 'type' => 'Ellipse', 'style' => 'D', 'line_style' => self::LINE_STYLE],
        ];
        $this->pdf->draw($line_map, ['circle' => 1, 'ellipse' => 1]);
    }

    private function drawBank()
    {
        if (is_null($this->oas_config)) {
            return;
        }
        $data = [
            'bank'   => $this->oas_config->bank,
            'shiten' => $this->oas_config->branch,
            'koza'   => $this->oas_config->account_number
        ];
        $ary = [
            ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'bank',   'suffix' => '', 'x' => 114.5, 'y' =>   240, 'type' => 'Cell', 'width' => 28.8, 'height' => 7.3, 'align' => 'C', 'flg' => true],
            ['font' => $this->mincho, 'style' => '', 'size' =>  9, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'shiten', 'suffix' => '', 'x' => 157.5, 'y' =>   240, 'type' => 'Cell', 'width' =>   25, 'height' => 7.3, 'align' => 'C', 'flg' => true],
            ['font' => $this->mono,   'style' => '', 'size' => 10, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'koza',   'suffix' => '', 'x' => 124.0, 'y' => 256.5, 'type' => 'Cell', 'width' => 68.5, 'height' => 4.9, 'align' => 'L', 'flg' => true, 'pitch' => 3.0],
        ];
        $this->pdf->draw($ary, $data);

        $x1 = self::PDF_ELLIPSE_MAP['bank_type'][$this->oas_config->bank_type]['x'];
        $x2 = self::PDF_ELLIPSE_MAP['branch_type'][$this->oas_config->branch_type]['x'];
        $x3 = self::PDF_ELLIPSE_MAP['account_type'][$this->oas_config->account_type];
        $y1 = self::PDF_ELLIPSE_MAP['bank_type'][$this->oas_config->bank_type]['y'];
        $y2 = self::PDF_ELLIPSE_MAP['branch_type'][$this->oas_config->branch_type]['y'];
        $y3 = 253.3;
        $line_map = [
            ['name' => 'ellipse', 'x' => $x1, 'y' => $y1, 'rx' => 2.6, 'ry' => 1.6, 'angle' => 0, 'astart' => 0, 'afinish' => 360, 'type' => 'Ellipse', 'style' => 'D', 'line_style' => self::LINE_STYLE],
            ['name' => 'ellipse', 'x' => $x2, 'y' => $y2, 'rx' => 2.5, 'ry' => 1.6, 'angle' => 0, 'astart' => 0, 'afinish' => 360, 'type' => 'Ellipse', 'style' => 'D', 'line_style' => self::LINE_STYLE],
            ['name' => 'circle',  'x' => $x3, 'y' => $y3, 'r' => 1.5, 'astart' => 0, 'angend' => 360, 'type' => 'Circle', 'style' => 'D', 'line_style' => self::LINE_STYLE],
        ];
        $this->pdf->draw($line_map, ['circle' => 1, 'ellipse' => 1]);
    }

    public function drawDetail($target_year)
    {
        $start = date("Y", strtotime($target_year));
        $sql = "SELECT * FROM `table::account_book`
                 WHERE userkey = ? AND year = ?";
        if (!$this->db->query($sql, [$this->uid, $start])) {
            return false;
        }
        $result = $this->db->fetch();

        $step = 0;
        $total = 0;
        $data = [];
        foreach ((array)$result as $key => $val) {
            if (in_array($key, ['year','userkey','locked','modify_date'])) {
                continue;
            }
            if ($key === 'col_01') $step++;
            if ($key === 'col_10') $this->pension = $val;
            if ($key === 'col_11') $this->mutualaid = $val;
            if ($key === 'col_12') $this->lifeinsurance = $val;
            if ($key === 'col_23') $val = $this->medicalCostDeduction($data['col_09'], $val);
            $data[$key] = $val;
            if ($step > 0) {
                $total += (int)$data[$key];
            }
            if ($key === 'col_07') {
                $data['col_08'] = round($data['bol_09'] + (($data['bol_10'] + $data['bol_11']) * 0.5));
                $total += $data['col_08'];
                $data['col_09'] = $total;
                $total = 0;
                $data['col_25'] = 0;
            } elseif ($key === 'col_24') {
                $data['col_25'] = $total;
            }
        }

        ksort($data);

        $y = 62.8;
        $x = 58.5;
        $w = 44.7;
        $h = 6.26;
        $t = 2.80;
        $ary = [];
        foreach ($data as $key => $val) {
            if ($key === 'col_16' || $key === 'col_18') continue;
            if (preg_match("/^col_(14|15|16|17|18|19|20)$/", $key)) {
                $data[$key] = floor($data[$key] / 10000);
                $w = 24.5;
            } else {
                $w = 44.7;
            }
            if (preg_match("/^col_(01|09|21|25)$/", $key)) {
                $y += 0.22;
            }
            if (empty($data[$key])) {
                unset($data[$key]);
            }
            $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 11, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => $key, 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];
            $y += $h;
        }

        $y = 62.8;
        $x = 149;
        $total = $data['col_09'] - $data['col_25'];
        $data['total'] = floor($total / 1000);
        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 11, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'total', 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => 29.5, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];
        $y += $h;

        if ($total < 1000) {
            $data['tax'] = 0;
        } else if ($total < 1950000) {
            $data['tax'] = $total * 0.05;
        } else if ($total < 3300000) {
            $data['tax'] = $total * 0.1 - 97500;
        } else if ($total < 6950000) {
            $data['tax'] = $total * 0.2 - 427500;
        } else if ($total < 9000000) {
            $data['tax'] = $total * 0.23 - 636000;
        } else if ($total < 18000000) {
            $data['tax'] = $total * 0.33 - 1536000;
        } else {
            $data['tax'] = $total * 0.4 - 2796000;
        }
        $data['tax'] = floor($data['tax'] / 100) * 100;
        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 11, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'tax', 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];
        $y += $h * 6;

        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 11, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'tax', 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];
        $y += $h * 2;

        $data['col_40'] = $data['tax'];
        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 11, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'col_40', 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];
        $y += $h;

        $data['col_41'] = floor($data['tax'] * 0.021);
        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 11, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'col_41', 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];
        $y += $h;

        $data['col_42'] = $data['col_40'] + $data['col_41'];
        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 11, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'col_42', 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];
        $y += $h * 5 + .22;

        $data['col_47'] = floor($data['col_42'] / 100);
        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 11, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'col_47', 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => 34.5, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];
        $y += $h * 4 + .22;

        $data['col_51'] = $this->oas_config->blue_return_deduction;
        $ary[] = ['font' => $this->mono, 'style' => '', 'size' => 11, 'color' => self::TEXT_COLOR, 'prefix' => '', 'name' => 'col_51', 'suffix' => '', 'x' => $x, 'y' => $y, 'type' => 'Cell', 'width' => $w, 'height' => $h, 'align' => 'R', 'flg' => true, 'pitch' => $t];

        $this->pdf->draw($ary, $data);
    }

    private function medicalCostDeduction($income, $medicalcost, $insurance = 0)
    {
        $c = $medicalcost - $insurance;
        $e = floor($income * 0.05);
        $f = min($e, 1000000);
        $g = $c - $f;
        if ($g < 0) $g = 0;
        return min(2000000, $g);
    }
}

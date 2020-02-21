<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2019 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms;

/**
 * Site management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Oas extends User implements PackageInterface
{
    /*
     * Using common accessor methods
     */
    use Accessor;

    /**
     * Application default mode.
     */
    const DEFAULT_MODE = 'oas.transfer.response';

    protected $command_convert = null;

    protected $tax_rate = null;
    protected $reduced_tax_rate = null;

    protected $pdf;
    protected $pdf_meta;

    /**
     * fonts for TCPDF
     */
    protected $mincho  = 'ipamp';
    protected $gothic  = 'ipagp';
    protected $mono    = 'ocrb';

    protected $oas_config;

    /**
     * Object constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->tax_rate = (float)$this->app->cnf('srm:tax_rate');
        $this->reduced_tax_rate = (float)$this->app->cnf('srm:reduced_tax_rate');

        if (class_exists('Imagick')) {
            $this->command_convert = 'imagick';
        } else {
            $convert = $this->app->cnf('external_command:convert');
            if (!empty($convert)) {
                $disable_functions = \P5\Text::explode(',', ini_get('disable_functions'));
                if (!in_array('exec', $disable_functions)) {
                    exec('convert --version', $output, $status);
                    if ($status === 0) {
                        $this->command_convert = $convert;
                    }
                }
            }
        }

        $conf_file = $this->privateSavePath() . '/oas_config.json';
        if (file_exists($conf_file)) {
            $this->oas_config = json_decode(file_get_contents($conf_file));
            if (isset($this->oas_config->pdf_meta)) {
                $this->pdf_meta = $this->oas_config->pdf_meta;
            }
        }
    }

    /**
     * Default Mode
     *
     * @final
     * @param Tms\App $app
     *
     * @return string
     */
    final public static function getDefaultMode($app)
    {
        $mode = $app->cnf('application:default_mode');
        return (!empty($mode)) ? $mode : self::DEFAULT_MODE;
    }

    /**
     * This package name
     *
     * @final
     *
     * @return string
     */
    final public static function packageName()
    {
        return strtolower(stripslashes(str_replace(__NAMESPACE__, '', __CLASS__)));
    }

    /**
     * Application name
     *
     * @final
     *
     * @return string
     */
    final public static function applicationName()
    {
        return \P5\Lang::translate('APPLICATION_NAME');
    }

    /**
     * Application label
     *
     * @final
     *
     * @return string
     */
    final public static function applicationLabel()
    {
        return \P5\Lang::translate('APPLICATION_LABEL');
    }

    /**
     * This package version
     *
     * @final
     *
     * @return string
     */
    final public static function version()
    {
        return System::getVersion(__CLASS__);
    }

    /**
     * This package version
     *
     * @final
     *
     * @return string|null
     */
    final public static function templateDir()
    {
        return __DIR__.'/'.\Tms\View::TEMPLATE_DIR_NAME;
    }

    /**
     * Unload action
     *
     * Clear session data for package,
     * when unload application
     */
    public static function unload()
    {
        // NoP
    }

    /**
     * Clear application level permissions.
     *
     * @see Tms\User::updatePermission()
     *
     * @param Tms\Db $db
     * @param int    $userkey
     *
     * @return bool
     */
    public static function clearApplicationPermission(Db $db, $userkey)
    {
        $filter1 = [0];
        $filter2 = [0];
        $statement = 'userkey = ? AND application = ?'
            .' AND filter1 IN ('.implode(',', array_fill(0, count($filter1), '?')).')'
            .' AND filter2 IN ('.implode(',', array_fill(0, count($filter2), '?')).')';
        $options = array_merge([$userkey, self::packageName()], $filter1, $filter2);

        return $db->delete('permission', $statement, $options);
    }

    /**
     * Reference permission.
     *
     * @todo Better handling for inheritance
     *
     * @see Tms\User::hasPermission()
     *
     * @param string $key
     * @param int    $filter1
     * @param int    $filter2
     *
     * @return bool
     */
    public function hasPermission($key, $filter1 = 0, $filter2 = 0)
    {
        return parent::hasPermission($key, $filter1, $filter2);
    }

    public function init()
    {
        parent::init();
        $config = $this->view->param('config');
        $this->view->bind('config', $config);
    }

    public function availableConvert()
    {
        return !empty($this->command_convert);
    }

    protected function pathToID($path)
    {
        return trim(str_replace(['/','.'], ['-','_'], preg_replace('/\.html?$/','',$path)), '-_');
    }

    public function receipts()
    {
        $receipts = $this->db->select(
            'id ,title',
            'receipt_template',
            'WHERE userkey = ?',
            [$this->uid]
        );

        foreach ($receipts as &$receipt) {
            $receipt['active'] = ($receipt['id'] === $this->session->param('receipt_id')) ? 1 : 0;
        }
        unset($receipt);

        return $receipts;
    }

    public function callFromTemplate()
    {
        $params = func_get_args();
        $function = array_shift($params);
        $function = \Tms\Base::lowerCamelCase($function);

        if (method_exists($this, $function)) {
            return call_user_func_array([$this, $function], $params);
        }

        return null;
    }

    private function bankList()
    {
        $banks = $this->db->select(
            'account_number,bank,branch,account_type',
            'bank', 'WHERE userkey = ?', [$this->uid]
        );

        $bank_list = [];
        foreach ($banks as $bank) {
            $bank_list[] = [
                'label' => $bank['bank'].' '.$bank['branch'],
                'value' => $bank['account_number'],
            ];
        }

        return $bank_list;
    }

    /**
     * PDF Path
     *
     * @param  number   $year
     * @param  string   $category
     * @param  string   $fileName
     * @return string
     */
    protected function getPdfPath($year, $category, $fileName)
    {
        $format = (!empty($this->oas_config->pdf_save_format))
            ? $this->oas_config->pdf_save_format : null;
        if (empty($format)) {
            $format = $this->privateSavePath() . '/%s/%s/%s';
        }
        return sprintf($format, $year, $category, $fileName);
    }

    public function outputPdf($file, $savePath = null, $display = false, $locked = false)
    {
        // Set Author
        if (!empty($this->pdf_meta)) {
            $this->pdf->setMetaData((array)$this->pdf_meta);
        }

        // Security
        $this->pdf->encrypt(['copy', 'modify']);

        if (empty($savePath)) {
            $this->pdf->Output($file, 'I');
        } else {
            // Create Directory
            if (!file_exists($savePath) && false === mkdir($savePath, 0777, true)) {
                return false;
            }

            $pdf = "{$savePath}/{$file}";
            // Save File
            if (!file_exists($pdf) || is_writable($pdf)) {
                $this->pdf->Output($pdf, 'F');
            }
            if (false !== $locked) {
                chmod($pdf, 0440);
            }
            if ($display !== true) {
                return file_exists($pdf);
            }
            header("Content-Type: application/pdf");
            readfile($pdf);
        }
        exit;
    }

    static public function toWareki($date, $with_gengo = false)
    {
        $timestamp = (is_string($date)) ? strtotime($date) : $date;
        $year = date('Y', $timestamp);
        $offset = ($year < 2019) ? 1988 : 2018;
        $gengo = '';
        if ($with_gengo) {
            $gengo = ($year < 2019) ? 'H.' : 'R.';
        }

        return $gengo . ($year - $offset);
    }

    protected function privateSavePath()
    {
        return sprintf('%s/%s', $this->app->cnf('data_dir'), md5($this->uid));
    }
}

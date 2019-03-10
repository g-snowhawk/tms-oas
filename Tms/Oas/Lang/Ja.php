<?php
/**
 * This file is part of Tak-Me System.
 *
 * Copyright (c)2019 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Tms\Oas\Lang;

/**
 * Japanese Languages for Tms.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Ja extends \P5\Lang
{
    const APP_NAME = 'OAS';
    const ALT_NAME = '会計管理';

    const APPLICATION_NAME = self::APP_NAME;
    const APPLICATION_LABEL = self::ALT_NAME;
    const APP_DETAIL    = self::ALT_NAME.'機能を提供します。';
    const SUCCESS_SETUP = self::ALT_NAME.'機能の追加に成功しました。';
    const FAILED_SETUP  = self::ALT_NAME.'機能の追加に失敗しました。';

    const LABEL_CASH = '現金';
    const YEN = '円';

    const LIST_HEADER_1 = 'No';
    const LIST_HEADER_2 = '勘定科目';
    const LIST_HEADER_3 = '摘要';
    const LIST_HEADER_4 = '支払期限';
    const LIST_HEADER_5 = '編<br />集<br />';

    const EDIT = '編集';

    const DATE_FORMAT   = 'Y年m月d日';
    const DATE_FORMAT_N = '%4d年%2d月%2d日';
    const DATE_FORMAT_H = '平成%3d年%3d月%3d日';
    const DATE_FORMAT_R = '令和%3d年%3d月%3d日';

    const BRACKETS_LEFT  = '（';
    const BRACKETS_RIGHT = '）';
    const IDENTICAL      = '〃';
    const DEBT           = '借';
    const LOAN           = '貸';
    const COMMA          = '、';

    const TO_NEXT_PAGE   = '次頁へ繰越';
    const FROM_PRIV_PAGE = '前頁より繰越';

    const SHOKUCHI = '諸口';
    const TB_TOTAL = '合計';

    const LG_MONTH = '月計';
    const LG_TOTAL = '累計';
    const LG_NEXT = '次月繰越';
    const LG_PREV = '前月繰越';
    const LG_NEXT_STAGE = '次期繰越';
    const LG_THIS_STAGE = '当期残高';

    const FROM_PRIV_YEAR = '前年より繰越';
    const DEPRECIATION   = '本年分減価償却費';
    const ACQUIRE        = '新規購入';

    const KOKUMINNENKIN = '国民年金';
    const SHOKIBOKYOSAI = '小規模企業共済';

    const SOLD = '売却';
    const SALE_PRICE = '売却額';
    const LOSS_ON_SALE = '売却損';
    const GAIN_ON_SALE = '売却損';
    const RETIREMENT = '除却';
}

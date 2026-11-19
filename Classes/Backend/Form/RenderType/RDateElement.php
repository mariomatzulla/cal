<?php

declare(strict_types=1)
;

namespace MarioMatzulla\Cal\Backend\Form\RenderType;

use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class RDateElement
 */
class RDateElement extends AbstractCalFormElementHelper
{
    /**
     * @return array
     * @throws \TYPO3\CMS\Backend\Form\Exception
     */
    public function render(): array
    {

        $result = $this->initializeResultArray();

        $table = $this->data ['tableName'];
        $row = $this->data ['databaseRow'];

        $uid = $row ['uid'];

        $rdateType = $row ['rdate_type'] [0];
        $rdateValues = GeneralUtility::trimExplode(',', (string)$row ['rdate'], true);

        // add for new empty line
        $rdateValues [] = '';

        $out = [ ];
        $out [] = '<div class="formengine-field-item t3js-formengine-field-item">';
        $out [] = '<div class="form-wizards-wrap">';
        $out [] = '<div class="form-wizards-element" id="rdate-container">';
        $out [] = '<div class="form-control-wrap">';

        /** @var NodeFactory $nodefactory */
        $nodefactory = GeneralUtility::makeInstance(NodeFactory::class);

        $key = 0;
        foreach ($rdateValues as $value) {
            $out [] = '<div class="rdate-element">';
            $formatedValue = '';
            if ($value !== '') {

                $m = [ ];
                preg_match('/(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})?/i', $value, $m);
                $formatedValue = sprintf('%02d-%02d-%02d %02d:%02d:%02d', $m [1], $m [2], $m [3], $m [4], $m [5], $m [6]);
            }
            $config = [
                'tableName' => 'tx_cal_event',
                'fieldName' => 'rdate' . $key,
                'databaseRow' => [
                    'uid' => $uid,
                ],
                'processedTca' => [
                    'columns' => [
                        'rdate' . $key => [
                            'config' => [
                                'type' => 'input',
                                'customCssClass' => 'rdate-change-action',
                            ],
                        ],
                    ],
                ],
                'renderType' => 'datetime',
                'parameterArray' => [
                    'itemFormElValue' => null,
                    'itemFormElName' => 'data[' . $table . '][' . $uid . '][rdate' . $key . ']',
                    'fieldConf' => [
                        'config' => [
                            'type' => 'datetime',
                            'format' => 'XXX',
                        ],
                    ],
                ],
            ];

            if ($formatedValue !== '') {
                $config ['parameterArray'] ['itemFormElValue'] = new \DateTime($formatedValue);
            }

            if ($rdateType === 'date_time' || $rdateType === 'period') {
                $config ['parameterArray'] ['fieldConf'] ['config'] ['format'] = 'datetime';
                $config ['processedTca'] ['columns'] ['rdate' . $key] ['config'] ['format'] = 'datetime';
                $datefield = $nodefactory->create($config)->render();
                $out [] = '<div class="cal-rdate-datetime-rendering">' . $datefield ['html'] . '</div>';
            } else {
                $config ['parameterArray'] ['fieldConf'] ['config'] ['format'] = 'date';
                $config ['processedTca'] ['columns'] ['rdate' . $key] ['config'] ['format'] = 'date';
                $datefield = $nodefactory->create($config)->render();

                $out [] = $datefield ['html'];
            }

            if ($rdateType === 'period') {
                $periodArray = [ ];
                if (is_string($value)) {
                    preg_match('/P((\d+)Y)?((\d+)M)?((\d+)W)?((\d+)D)?T((\d+)H)?((\d+)M)?((\d+)S)?/', $value, $periodArray);
                }
                while (count($periodArray) < 13) {
                    array_push($periodArray, 0);
                }

                $out [] = '<label class="t3js-formengine-label">' . $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:l_duration') . '</label>'
                    . '<div class="row">'
                        . '<div class="form-group t3js-formengine-validation-marker t3js-formengine-palette-field col-sm-2">'
                            . '<label class="t3js-formengine-label">' . $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:l_year') . '</label>'
                                . '<div class="formengine-field-item t3js-formengine-field-item"><div class="form-control-wrap" style="max-width: 192px">'
                                    . '<input type="text" value="' . (int)($periodArray [2]) . '" class="form-control rdateChanged rdate-change-action" name="rdateYear' . $key . '" id="rdateYear' . $key . '"/>'
                                . '</div>' . '</div>' . '</div>'
                        . '<div class="form-group t3js-formengine-validation-marker t3js-formengine-palette-field col-sm-2">'
                            . '<label class="t3js-formengine-label">' . $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:l_month') . '</label>'
                                . '<div class="formengine-field-item t3js-formengine-field-item"><div class="form-control-wrap" style="max-width: 192px">'
                                    . '<input type="text" value="' . (int)($periodArray [4]) . '" class="form-control rdateChanged rdate-change-action" name="rdateMonth' . $key . '" id="rdateMonth' . $key . '"/>'
                                . '</div>' . '</div>' . '</div>'
                        . '<div class="form-group t3js-formengine-validation-marker t3js-formengine-palette-field col-sm-2">'
                            . '<label class="t3js-formengine-label">' . $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:l_week') . '</label>'
                                . '<div class="formengine-field-item t3js-formengine-field-item"><div class="form-control-wrap" style="max-width: 192px">'
                                    . '<input type="text" value="' . (int)($periodArray [6]) . '" class="form-control rdateChanged rdate-change-action" name="rdateWeek' . $key . '" id="rdateWeek' . $key . '"/>'
                            . '</div>' . '</div>' . '</div>'
                         . '<div class="form-group t3js-formengine-validation-marker t3js-formengine-palette-field col-sm-2">'
                             . '<label class="t3js-formengine-label">' . $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:l_day') . '</label>'
                                 . '<div class="formengine-field-item t3js-formengine-field-item"><div class="form-control-wrap" style="max-width: 192px">'
                                     . '<input type="text" value="' . (int)($periodArray [8]) . '" class="form-control rdateChanged rdate-change-action" name="rdateDay' . $key . '" id="rdateDay' . $key . '"/>'
                                 . '</div>' . '</div>' . '</div>'
                         . '<div class="form-group t3js-formengine-validation-marker t3js-formengine-palette-field col-sm-2">'
                             . '<label class="t3js-formengine-label">' . $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:l_hour') . '</label>'
                                 . '<div class="formengine-field-item t3js-formengine-field-item"><div class="form-control-wrap" style="max-width: 192px">'
                                     . '<input type="text" value="' . (int)($periodArray [10]) . '" class="form-control rdateChanged rdate-change-action" name="rdateHour' . $key . '" id="rdateHour' . $key . '"/>'
                                 . '</div>' . '</div>' . '</div>'
                         . '<div class="form-group t3js-formengine-validation-marker t3js-formengine-palette-field col-sm-2">'
                             . '<label class="t3js-formengine-label">' . $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:l_minute') . '</label>'
                                 . '<div class="formengine-field-item t3js-formengine-field-item"><div class="form-control-wrap" style="max-width: 192px">'
                                     . '<input type="text" value="' . (int)($periodArray [12]) . '" class="form-control rdateChanged rdate-change-action" name="rdateMinute' . $key . '" id="rdateMinute' . $key . '"/>'
                                 . '</div>' . '</div>' . '</div>'
                      . '</div>';
            }
            $out [] = '<a href="#" class="remove-rdate">' . $this->getGarbageIcon() . $this->getLanguageService()->sL('LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.remove_recurrence') . '</a>';
            $out [] = '</div>';
            $key++;
        }
        // Until we can reset the datetime picker we have to disable this option and let a new field be generated during save action.
        //$out [] = '<div style="padding: 5px 0"><a href="#" class="add-rdate">' . $this->getNewIcon() . $this->getLanguageService()->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xlf:tx_cal_event.add_recurrence' ) . '</a></div>';
        $out [] = '<input type="text" name="data[' . $table . '][' . $uid . '][rdate]" id="data_' . $table . '_' . $uid . '_rdate" value="' . $row ['rdate'] . '" />';

        $out [] = '</div>';
        $out [] = '</div>';
        $out [] = '</div>';
        $out [] = '</div>';

        $result ['html'] = implode("\n", $out);

        $result['javaScriptModules'] = [
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RecurUI/RDate.js'),
            JavaScriptModuleInstruction::create('@MarioMatzulla/Cal/RegisterRdateChangeListener.js'),
        ];

        return $result;
    }
}

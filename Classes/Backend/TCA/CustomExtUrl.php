<?php


/**
 * This file is part of the TYPO3 extension Calendar Base (cal).
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 extension Calendar Base (cal) project - inspiring people to share!
 */
  
namespace TYPO3\CMS\Cal\Backend\TCA;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Backend class for user-defined TCA type used in recurring event setup.
 *
 * @author Mario Matzulla <mario@matzullas.de>
 * @package TYPO3
 * @subpackage cal
 */
class CustomExtUrl extends AbstractFormElement {
  
  protected $defaultFieldWizard = [
      'localizationStateSelector' => [
          'renderType' => 'localizationStateSelector',
      ],
      'otherLanguageContent' => [
          'renderType' => 'otherLanguageContent',
          'after' => [
              'localizationStateSelector'
          ],
      ],
      'defaultLanguageDifferences' => [
          'renderType' => 'defaultLanguageDifferences',
          'after' => [
              'otherLanguageContent',
          ],
      ],
  ];
  
  private $garbageIcon;
  
  private $newIcon;
  
  private function init() {
    $this->garbageIcon = '<span class="t3-icon fa t3-icon fa fa-trash"> </span>';
    $languageService = $this->getLanguageService();
    $this->newIcon = '<span title="' . $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_event.add_recurrence' ) . '" class="t3-icon fa t3-icon fa fa-plus-square"> </span>';
  }
  
  public function render() {
    $this->init();
    $resultArray = $this->initializeResultArray();
    
    $fieldWizardResult = $this->renderFieldWizard();
    $fieldWizardHtml = $fieldWizardResult['html'];
    $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);
    
    $languageService = $this->getLanguageService();
    
    $mainFieldHtml = [];
    $mainFieldHtml[] = "<script type=\"text/javascript\">function ExtUrlUI (containerID, storageID, rowClass, rowHTML) {
	this.containerID = containerID;
	this.storageID = storageID;
	this.rowClass = rowClass;
	this.rowHTML = rowHTML;
};

ExtUrlUI.prototype = {

	addUrl: function(defaultNote, defaultUrl){
		var container = document.getElementById(escapeRegExp(this.containerID));

		container.insertAdjacentHTML('beforeend', this.rowHTML);

		if(defaultUrl) {
            var queryResult = document.getElementById(escapeRegExp(this.containerID)).querySelector('input[type=\"text\"]');
            if(queryResult) {
			 queryResult[queryResult.length-1].value = defaultUrl;
            }
		}
		if(defaultNote) {
            var queryResult = document.getElementById(escapeRegExp(this.containerID)).querySelector('input[type=\"text\"]');
            if(queryResult) {
			 queryResult[queryResult.length-2].value = defaultNote;
            }
		}

		this.save();
	},

	removeUrl: function(icon) {
        icon.parentNode.remove();
		this.save();
	},

	save: function() {
		storage = document.getElementById(escapeRegExp(this.storageID));
		storage.value = '';

		storageNotes = document.getElementById(escapeRegExp(this.storageID.substr(0,this.storageID.length-1) + '_notes]'));
		storageNotes.value = '';

		var container = document.getElementById(escapeRegExp(this.containerID));
		var classElements = container.getElementsByClassName(this.rowClass);
        for(var i=0; i < classElements.length; i++) {
            var inputElements = classElements[i].getElementsByTagName('input');
            for (var j=0; j < inputElements.length; j++) {
				if (inputElements[j].className=='exturl') {
					if (storage.value) {
						storage.value += '\\n';
					}
					storage.value += inputElements[j].value;
				}
				if (inputElements[j].className=='exturlnote') {
					if (storageNotes.value) {
						storageNotes.value += '\\n';
					}
					storageNotes.value += inputElements[j].value;
				}
			}
		}
	},

	load: function() {
console.log(escapeRegExp(this.storageID));
		initialUrlValue = document.getElementById(escapeRegExp(this.storageID)).value;
		urlArray = initialUrlValue.split('\\n');
console.log(escapeRegExp(this.storageID.substr(0,this.storageID.length-1)+'_notes]'));
		initialNoteValue = document.getElementById(escapeRegExp(this.storageID.substr(0,this.storageID.length-1)+'_notes]')).value;
		noteArray = initialNoteValue.split('\\n');
		var obj = this;

		for(var i=0; i<urlArray.length; i++){
			obj.addUrl(noteArray[i],urlArray[i]);
		}
	},

};

function escapeRegExp(str) {
  return str.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, \"\\$&\");
}

function ExtUrlInstanceUI (containerID, storageID, rowClass, rowHTML) {
	ExtUrlUI.call(this, containerID, storageID, rowClass, rowHTML);
};
ExtUrlInstanceUI.prototype = Object.create(ExtUrlUI.prototype, {
	storageToHash: function(url) {
		urlValue = url;
		return { url: urlValue };
	}
});</script>";
    
    $languageService = $this->getLanguageService();
    
    $table = $this->data['tableName'];
    $row = $this->data['databaseRow'];
    $pid = $row ['pid'];
    $uid = $row ['uid'];
    
    $out = array ();
    $out [] = '<script type="text/javascript">';
    $out [] = "var extUrl = new ExtUrlInstanceUI('ext_url-container', 'data[" . $table . "][" . $uid . "][ext_url]', 'cal-row', '" . $this->getExtUrlRow() . "');";
    $out [] = "document.addEventListener('DOMContentLoaded', function(){ extUrl.load();}, false);";
    $out [] = '</script>';
    $out [] = '<input type="hidden" name="data[' . $table . '][' . $uid . '][ext_url_notes]" id="data[' . $table . '][' . $uid . '][ext_url_notes]" value="' . $row ['ext_url_notes'] . '" />';
    
    $out [] = '<div id="ext_url-container"></div>';
    $out [] = '<div style="padding: 5px 0px 5px 0px;"><a href="javascript:extUrl.addUrl();">' . $this->newIcon . $languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_calendar.add_url' ) . '</a></div>';
    $out [] = '<input type="hidden" name="data[' . $table . '][' . $uid . '][ext_url]" id="data[' . $table . '][' . $uid . '][ext_url]" value="' . $row ['ext_url'] . '" />';
    
    $mainFieldHtml[] = implode( chr( 10 ), $out );
    
    $resultArray['html'] = implode(LF, $mainFieldHtml);
    return $resultArray;
  }
  
  public function getExtUrlRow() {
    
    $languageService = $this->getLanguageService();
    
    $html = '<div class="cal-row">';
    $html .= '<label style="padding-right:3px;">'.$languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_calendar.ext_url_note' ) . ':</label><input type="text" class="exturlnote" onchange="extUrl.save()" style="min-width:30rem">';
    $html .= '<label style="margin-left:10px;padding-right:3px;">'.$languageService->sL( 'LLL:EXT:cal/Resources/Private/Language/locallang_db.xml:tx_cal_calendar.ext_url_url' ) . ':</label><input type="text" class="exturl" onchange="extUrl.save()" style="min-width:50rem" >';
    $html .= '<a id="garbage" href="#" onclick="extUrl.removeUrl(this);">' . $this->garbageIcon . '</a>';
    $html .= '</div>';
    
    return $this->removeNewLines( $html );
  }
  
  /**
   * Converts newlines to <br/> tags.
   *
   * @access private
   * @param
   *          string The input string to filtered.
   * @return string converted string.
   */
  private function removeNewlines($input) {
    
    $order = Array (
        
        "\r\n",
        "\n",
        "\r",
        "\t"
    );
    $replace = '';
    $newstr = str_replace( $order, $replace, $input );
    
    return $newstr;
  }
  
}

?>
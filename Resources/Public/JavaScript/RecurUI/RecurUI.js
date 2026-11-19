/**
 * Module: MarioMatzulla/Cal/RecurUI
 *
 * JavaScript to handle data import
 * @exports MarioMatzulla/Cal/RecurUI
 */
import $ from 'jquery';

export class RecurUI {
	
	constructor(containerID, storageID, rowClass, rowHTML) {
        this.containerID = containerID;
        this.storageID = storageID;
        this.rowClass = rowClass;
        this.rowHTML = rowHTML;
    }

    addRecurrence (defaultValues) {
        var container = $("#" + RecurUI.escapeRegExp(this.containerID));
        if (this.rowHTML == true) {
        	var newRow = document.querySelector("#" + RecurUI.escapeRegExp(this.containerID)+" .cal-row").cloneNode(true);
	        const hiddenTag = newRow.querySelector('a');
			hiddenTag.style.display = '';
	        container.append(newRow);
	    }

        if (defaultValues) {
            $.each(defaultValues, function (index, pair) {
                var element = container.find('select.' + index).last();
                element.val(pair);
            });
        }

        this.save();
    }

    setCheckboxes (defaultValues) {
        var container = $("#" + RecurUI.escapeRegExp(this.containerID));
        var rowSelector = '.' + this.rowClass;
        if (defaultValues) {
            $.each(defaultValues, function (index, pair) {
                container.find(rowSelector + ' input[value="' + pair + '"]').each(function (index, input) {
                    input.checked = "true";
                });
            });
        }
    }

    removeRecurrence (icon) {
	    if(icon.parentNode.parentNode.childElementCount > 2) {
	        icon.parentNode.remove();
	        this.save();
        }
    }

    save () {
        var storage = $("#" + RecurUI.escapeRegExp(this.storageID));
        storage.val('');
        //@todo  Figure out how to differentiate selector based forms from element based forms
        var rows = $("#" + RecurUI.escapeRegExp(this.containerID)+' .'+this.rowClass);
        rows.each(function (index, div) {
            var rowValue = '';
            var notComplete = false;
            $(div).find("select").each(function (index, select) {
                rowValue += $(select).val();
            });
            if(rowValue.length < 2 || (rowValue.startsWith('-') && rowValue.length < 3)) {
				rowValue = '';
			}
            $(div).find('input[type="checkbox"]:checked').each(function (index, input) {
                if ($(input).val()) {
					if (rowValue.length > 0) {
	                    rowValue += ','+$(input).val();
	                } else {
                    	rowValue += $(input).val();
					}
                }
            });

            if (rowValue && rowValue.trim().length > 0) {

                if (storage.val()) {
                    storage.val(storage.val() + ',');
                }
                storage.val(storage.val() + rowValue);
            }
            storage.val([...new Set(storage.val().split(","))].join(','));
        });
    }

    load () {
        var initialValue = $("#" + RecurUI.escapeRegExp(this.storageID)).val();
        const recurArray = [...new Set(initialValue.split(","))]
        var obj = this;

        $.each(recurArray, function (index, recur) {
	        if(recur != 'null') {
	            var hash = obj.storageToHash(recur);
	            if (obj.rowHTML) {
	                obj.addRecurrence(hash);
	            } else {
	                obj.setCheckboxes(hash);
	            }
            }
        });
    }

    static escapeRegExp(str) {
		if(str) {
        	return str.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");
        }
        return '';
    }


};

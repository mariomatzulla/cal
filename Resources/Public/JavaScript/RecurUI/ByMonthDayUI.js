/**
 * Module: MarioMatzulla/Cal/RecurUI/ByMonthDayUI
 *
 * JavaScript to handle data import
 * @exports MarioMatzulla/Cal/RecurUI/ByMonthDayUI
 */
import $ from 'jquery';

// 2. Deine eigene Modul-Klasse importieren
import { RecurUI } from '@MarioMatzulla/Cal/RecurUI/RecurUI.js';

export class ByMonthDayUI extends RecurUI{
	
	constructor(containerID, storageID, rowClass, rowHTML) {
        super(containerID, storageID, rowClass, rowHTML);
    }

    storageToHash(recur) {
        var dayValue = recur;
        return {day: dayValue};
    }

};

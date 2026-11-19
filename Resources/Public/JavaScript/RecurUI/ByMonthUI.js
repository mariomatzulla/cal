/**
 * Module: MarioMatzulla/Cal/RecurUI/ByMonthUI
 *
 * JavaScript to handle data import
 * @exports MarioMatzulla/Cal/RecurUI/ByMonthUI
 */
import $ from 'jquery';

// 2. Deine eigene Modul-Klasse importieren
import { RecurUI } from '@MarioMatzulla/Cal/RecurUI/RecurUI.js';

export class ByMonthUI extends RecurUI{
	
	constructor(containerID, storageID, rowClass, rowHTML) {
        super(containerID, storageID, rowClass, rowHTML);
    }

    storageToHash(recur) {
        var monthValue = recur;
        return {month: monthValue};
    }
};

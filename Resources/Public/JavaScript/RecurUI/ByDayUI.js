/**
 * Module: MarioMatzulla/Cal/RecurUI/ByDayUI
 *
 * @exports MarioMatzulla/Cal/RecurUI/ByDayUI
 */
    
import $ from 'jquery';

// 2. Deine eigene Modul-Klasse importieren
import { RecurUI } from '@MarioMatzulla/Cal/RecurUI/RecurUI.js';

/**
 * @exports MarioMatzulla/Cal/RecurUI/ByDayUI
 */
export class ByDayUI extends RecurUI {
    
    constructor(containerID, storageID, rowClass, rowHTML) {
        super(containerID, storageID, rowClass, rowHTML);
    }

    storageToHash(recur) {
        var splitLocation = 0;
        if (recur.length > 2) {
            for (var i = 1; i < recur.length; i++) {
                var character = recur.charAt(i);
                if (((character < "0") || (character > "9")) && (character !== '-')) {
                    splitLocation = i;
                    break;
                }
            }
        }

        var countValue = recur.substr(0, splitLocation);
        var dayValue = recur.substr(splitLocation, recur.length);

        return {count: countValue, day: dayValue};
    }
}

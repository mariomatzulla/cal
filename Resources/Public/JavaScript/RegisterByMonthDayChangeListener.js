import { ByMonthDayUI } from '@MarioMatzulla/Cal/RecurUI/ByMonthDayUI.js';

document.addEventListener('change', function(event) {
    if (event.target.matches('.bymonthday-change-action')) {
        window.byMonthDay = new ByMonthDayUI('bymonthday-container', document.getElementById('bymonthday-container').firstElementChild.attributes.id.nodeValue, 'cal-row', false)
        byMonthDay.save();
    }
    if (event.target.matches('.bymonthday-select-change-action')) {
        window.byMonthDay = new ByMonthDayUI('bymonthday-container', document.getElementById('bymonthday-container').firstElementChild.attributes.id.nodeValue, 'cal-row', true)
        byMonthDay.save();
    }
});

document.addEventListener('click', function(event) {
    // Prüfen, ob das Event von unserer Checkbox ausgelöst wurde
    if (event.target.matches('.add-bymonthday')) {
	    event.preventDefault();
	    byMonthDay.addRecurrence();
    }
    if (event.target.matches('.remove-bymonthday')) {
	    event.preventDefault();
	    byMonthDay.removeRecurrence(event.target);
    }
});


window.byMonthDay = new ByMonthDayUI('bymonthday-container', document.getElementById('bymonthday-container').firstElementChild.attributes.id.nodeValue, 'cal-row', document.getElementById('bymonthday-container').getElementsByTagName('select').length > 0?true:false)
byMonthDay.load();
byMonthDay.save();
//document.querySelector('#bymonthday-container .remove-bymonthday').style.display = 'none';
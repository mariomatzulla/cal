import { ByDayUI } from '@MarioMatzulla/Cal/RecurUI/ByDayUI.js';

document.addEventListener('change', function(event) {
    if (event.target.matches('.byday-change-action')) {
        window.byDay = new ByDayUI('byday-container', document.getElementById('byday-container').firstElementChild.attributes.id.nodeValue, 'cal-row', false)
        byDay.save();
    }
    
    if (event.target.matches('.byday-select-change-action')) {
        window.byDay = new ByDayUI('byday-container', document.getElementById('byday-container').firstElementChild.attributes.id.nodeValue, 'cal-row', true)
        byDay.save();
    }
});

document.addEventListener('click', function(event) {
    if (event.target.matches('.add-byday')) {
		event.preventDefault();
	    byDay.addRecurrence();
    }
    if (event.target.matches('.remove-byday')) {
	    event.preventDefault();
	    byDay.removeRecurrence(event.target);
    }
});


window.byDay = new ByDayUI('byday-container', document.getElementById('byday-container').firstElementChild.name, 'cal-row', document.getElementById('byday-container').getElementsByTagName('select').length > 0?true:false )
byDay.load();
byDay.save();
document.querySelector('#byday-container .remove-byday').style.display = 'none';

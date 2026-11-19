import { ByMonthUI } from '@MarioMatzulla/Cal/RecurUI/ByMonthUI.js';

document.addEventListener('change', function(event) {
    if (event.target.matches('.bymonth-change-action')) {
        window.byMonth = new ByMonthUI('bymonth-container', document.getElementById('bymonth-container').firstElementChild.attributes.id.nodeValue, 'cal-row', false)
        byMonth.save();
    }
    
    if (event.target.matches('.bymonth-select-change-action')) {
        window.byMonth = new ByMonthUI('bymonth-container', document.getElementById('bymonth-container').firstElementChild.attributes.id.nodeValue, 'cal-row', true)
        byMonth.save();
    }
});


window.byMonth = new ByMonthUI('bymonth-container', document.getElementById('bymonth-container').firstElementChild.name, 'cal-row', document.getElementById('bymonth-container').getElementsByTagName('select').length > 0?true:false )
byMonth.load();
byMonth.save();

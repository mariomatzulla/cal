import { RDate } from '@MarioMatzulla/Cal/RecurUI/RDate.js';

document.addEventListener('change', function(event) {
    if (event.target.matches('.rdate-change-action') || (event.target.closest('.cal-rdate-datetime-rendering') && event.target.attributes.type.nodeValue == 'hidden')) {
		const element = document.getElementById('rdate-container').children[0].lastElementChild;
		const parts = element.id.split('_');
		const recordId = parts.at(-2);
		
		const table = element.id.match(/^data_(.+)_\d+_rdate$/)[1];
		
		const selectElement = document.querySelector('select[name="data['+table+']['+recordId+'][rdate_type]"]');
		const rdateType = selectElement.value;
		
        window.rDate = new RDate('%d-%m-%Y', table, recordId, rdateType);
    	rDate.rdateChanged();
    }
});

document.addEventListener('click', function(event) {
    if (event.target.matches('.add-rdate')) {
		event.preventDefault();
		
		const element = document.getElementById('rdate-container').children[0].lastElementChild;
		const parts = element.id.split('_');
		const recordId = parts.at(-2);
		
		const table = element.id.match(/^data_(.+)_\d+_rdate$/)[1];
		
		const selectElement = document.querySelector('select[name="data['+table+']['+recordId+'][rdate_type]"]');
		const rdateType = selectElement.value;
		
        window.rDate = new RDate('%d-%m-%Y', table, recordId, rdateType);
	    rDate.addRecurrence();
    }
    if (event.target.matches('.remove-rdate')) {
	    event.preventDefault();
	    const element = document.getElementById('rdate-container').children[0].lastElementChild;
		const parts = element.id.split('_');
		const recordId = parts.at(-2);
		
		const table = element.id.match(/^data_(.+)_\d+_rdate$/)[1];
		
		const selectElement = document.querySelector('select[name="data['+table+']['+recordId+'][rdate_type]"]');
		const rdateType = selectElement.value;
		
        window.rDate = new RDate('%d-%m-%Y', table, recordId, rdateType);
	    rDate.removeRecurrence(event.target);
    }
});

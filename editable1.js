$( document ).ready(function() {
  $('#editableTable1').SetEditable(
	  {
	  //NOTE: column number to adapt if columns are added or removed
	  columnsEd: "11",	//11 by default  
	  onEdit: function(columnsEd) {
		var empId = columnsEd[0].childNodes[0].innerText;
		var notes = columnsEd[0].childNodes[11].innerHTML;
		
		//get dom-target Text
		var div = document.getElementById("dom-target");
		var myData = div.textContent;
		
		var divjob = document.getElementById("jjob-target1");
		var myJob = divjob.textContent;
		
		$.ajax({
			type: 'POST',			
			url : "notesaction.php",	
			dataType: "json",					
			data: {id:empId, newnote:notes, jjob:myData, testset:myJob, action:'edit'},			
			success: function (response) {
				if(response.status) {
				}						
			}
		});
	  },
	  
	  onBeforeDelete: function(columnsEd) {
		var empId = columnsEd[0].childNodes[0].innerText;
		var div = document.getElementById("dom-target");
		var myData = div.textContent;
		var divjob = document.getElementById("jjob-target1");
		var myJob = divjob.textContent;
		$.ajax({
			type: 'POST',			
			url : "notesaction.php",
			dataType: "json",					
			data: {id:empId, jjob:myData, testset:myJob, action:'delete'},			
			success: function (response) {
				if(response.status) {
				}			
			}
		});
	  },
	  
	  onDelete: function(columnsEd) {
		document.location.reload(true);
	  },
	});
});
var regsecureq = {
	change: function()
	{
		var regq_id = $('regsecureq_id').value;
		this.spinner = new ActivityIndicator("body", {image: imagepath + "/spinner_big.gif"});
		new Ajax.Request('xmlhttp.php?action=change_regq&regq='+regq_id, {
			method: 'get',
			onComplete: function(request) { regsecureq.change_complete(request); }
		});
		document.body.style.cursor = 'wait';
		return false;
	},

	change_complete: function(request)
	{
		if(request.responseText.match(/<error>(.*)<\/error>/))
		{
			message = request.responseText.match(/<error>(.*)<\/error>/);

			if(!message[1])
			{
				message[1] = "An unknown error occurred.";
			}

			alert('There was an error changing the question.\n\n'+message[1]);
		}
		else if(request.responseText)
		{
			$('regsecureq_id').value = request.responseJSON.qid;
			$('regsecureq').update(request.responseJSON.q);
		}

		if(this.spinner)
		{
			this.spinner.destroy();
			this.spinner = '';
			document.body.style.cursor = 'default';
		}

		Element.removeClassName('regsecureans_status', "validation_success");
		Element.removeClassName('regsecureans_status', "validation_error");
		Element.removeClassName('regsecureans_status', "validation_loading");
		$('regsecureans_status').innerHTML = '';
		$('regsecureans_status').hide();
		$('regsecureans').className = 'textbox';
		$('regsecureans').value = '';
	}
};
$(document).ready(function() {

	const windowheight = $(window).height();
	const height = $(document).height();
	const foot = $('footer').height();

	console.log(height);
	console.log(windowheight);
	console.log(foot);

	if (height > windowheight)
	{
		$('footer').css('top', height-foot);
	}

	else {
		$('footer').css('top', windowheight-foot);
	}


	$('#searchWorks').click(function() {
		const rating = encodeURIComponent($('#rating').val());
		const genre = encodeURIComponent($('#genre').val());
		const title = encodeURIComponent($('#title').val());
		const wordCount = encodeURIComponent($("#wordcount").val());
		const req = "/library?rating=" + rating + "&genre=" + genre + "&title=" + title + "&wordcount=" + wordCount;
		console.log(req);
		$.get(req, function(data, status){
			data = data.split('<ul>');
			data = data[1].split('</ul>');
			const list = "<ul>" + data[0] + "</ul>";
        	$('ul').replaceWith(list);
        });
	});

    $("#chooseChapter").change(function(){

        let selected = $(this).children("option:selected").val();
				window.location = './chapter_' + selected;
    });

		$('#changePic').click(function() {
			console.log('lol what');
			$('#button-container').html("<input type = \"file\" name = \"photo\" />");
		});

})

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


	$('button').click(function() {
		const rating = encodeURIComponent($('#rating').val());
		const genre = encodeURIComponent($('#genre').val());
		const title = encodeURIComponent($('#title').val());
		const wordCount = encodeURIComponent($("#wordCount").val());
		const req = "/library?rating=" + rating + "&genre=" + genre + "&title=" + title + "&wordCount=" + wordCount;
		console.log(req);
		$.get(req, function(data, status){
			data = data.split('<ul>');
			data = data[1].split('</ul>');
			const list = "<ul>" + data[0] + "</ul>";
        	$('ul').replaceWith(list);
        });
	});
})

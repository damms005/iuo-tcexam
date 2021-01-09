let default_border = 'border-2';

$(function () {

	//space content
	$(' .content h1 , div.body h1 ').addClass('mt-5 mb-8')

	//change all radioboxes to pretty
	$("input[type='radio']").each(function (index, elmnt) {
		let closest_label = $(elmnt).siblings(`label[for='${$(elmnt).attr('id')}']`);
		if (closest_label) {
			$(elmnt).wrap('<div class="mb-5 pretty p-default p-round p-pulse" />');
			$(elmnt).closest('div').append(` <div class="state p-primary-o"> <label> ${$( closest_label ).text()} </label> </div> `);
			$(closest_label).remove();
		} else {
			console.log(` ${$(closest_label).attr('for')} != ${$(elmnt).attr('id')} `);
		}
	})

	//change all cheackboxes
	$("span.formw input[type='checkbox']").each(function (index, elmnt) {
		let closest_label = $(elmnt).closest('div').children(`label[for='${$(elmnt).attr('id')}']`);
		if (closest_label) {
			$(elmnt).wrap('<div class="pretty p-icon p-smooth" />');
			$(elmnt).closest('div').append(` <div class="state p-danger-o"> <i class="icon mdi mdi-check"></i> <label> ${$( closest_label ).text()} </label> </div> `);
			$(closest_label).remove();
		} else {
			console.log(` ${$(closest_label).attr('for')} != ${$(elmnt).attr('id')} `);
		}
	})
	$("input[type=checkbox]").each(function (ind, elmnt) {
		let filterOut = $(elmnt).parents("span.formw").length;
		if (filterOut == 0 && ((!!$(elmnt).attr('class')) == false || $(elmnt).attr('class') == '')) {
			$(elmnt).wrap('<div class="pretty p-icon p-smooth" />');
			$(elmnt).closest('div').append(` <div class="state p-danger-o"> <i class="icon mdi mdi-check"></i> <label>&nbsp;</label> </div> `);
			let sp = $(elmnt).closest('span');
			sp.css({
				backgroundColor: ''
			});
			sp.addClass('rounded border border-green-darkest p-1');
		}
	})

	//unstyled buttons, and button-like elements
	let stylable = ['buttongreen', 'xmlbutton', 'styledbutton'];
	$("input[type='submit'] , .xmlbutton , .testlist a.buttongreen , .styledbutton").each(function (index, el) {
		if ((!!$(el).attr('class')) == false || $(el).attr('class') == '' || stylable.indexOf(($(el).attr('class')) >= 0)) {
			$(el).removeClass('xmlbutton').addClass('border-blue ' + default_border + ' bg-white hover:bg-blue text-blue-dark hover:text-white hover:no-underline hover:rounded rounded no-underline p-2 mt-1 mb-1 mr-1 cursor-pointer')
		}
	})

	//input boxes unstyled
	$(" span.formw select , span.formw textarea , span.formw input[type='text'] , span.formw input[type='password'] , span.formw input[type='number']")
		.each(function (index, elmnt) {
			$(elmnt).addClass('form-control rounded')
		})

	//restylings for the questions page
	$('label[for="answertext"] , textarea#answertext').each(function (index, elmnt) {
		$(elmnt).addClass('m-4')
	});
	$('textarea#answertext').addClass('form-control rounded p-2').attr('placeholder', 'type your answer here');
	$('form#testform div.rowl').each(function (index, elmnt) {
		$(elmnt).addClass('p-4')
	});

	//style information boxes
	$("div.warning , div.error").removeClass('warning').removeClass('error').addClass("mt-5 mb-5 p-4 bg-red text-white rounded animated shake")
	$("div.message").removeClass('message').addClass("mt-5 mb-5 p-4 bg-pink-darkest text-white rounded animated shake")
	$("div.preview").removeClass('preview').addClass('rounded border bg-pink-lightest p-3')
	$("div.tceformbox").removeClass('tceformbox').addClass('rounded border bg-transparent p-3')
	//this is also the class for test form. So style it differntly
	$("div.tcecontentbox").each(function (ind, el) {
		let examTakingForm = $(el).closest('form#testform').length;
		if (examTakingForm > 0) {
			$("div.tcecontentbox").removeClass('tcecontentbox').addClass('rounded border bg-white p-4 animated fadeIn')
		} else {
			$("div.tcecontentbox").removeClass('tcecontentbox').addClass('rounded border bg-blue-lighter p-4 text-leading')
		}
	})


	//div.tceformbox
	$("div.tceformbox").addClass("m-5 p-5 rounded bg-blue animated fadeIn")

	//div.pagehelp
	$("div.pagehelp").removeClass('pagehelp').addClass("mt-4 p-3 rounded bg-blue-lighter")

	//hide unecesary elements
	$(".langselector").hide();
	$(".minibutton").hide();

	//do fine tooltips
	$("a, input, select, acronym").each(function (ind, el) {
		let title = $(el).attr('title');
		if (!!title) {
			$(el).attr("data-title", title);
			$(el).attr("data-toggle", "tooltip");
			$(el).attr("title", '');
		}
	})
	// $('[data-toggle="tooltip"]').tooltip({
	//     delay: { "show": 850, "hide": 50 },
	//     placement: 'auto'
	// });
	var HasTooltip = $('[data-toggle="tooltip"]');
	HasTooltip.on('enter focus mouseover', function (e) {
			e.preventDefault();
			var isShowing = $(this).data('isShowing');
			HasTooltip.removeData('isShowing');
			if (isShowing !== 'true') {
				HasTooltip.not(this).tooltip('hide');
				$(this).data('isShowing', "true");
				$(this).tooltip('show');
			} else {
				$(this).tooltip('hide');
			}
		})
		.on('blur mouseout leave mouseleave', function () {
			$(this).tooltip('hide');
		})
		.tooltip({
			animation: true,
			trigger: 'manual',
			delay: {
				"show": 850,
				"hide": 50
			}
		});

	//make tables more presentable
	$(".userselect").addClass('table table-bordered table-hover table-striped'); //results table
	$(".testlist").addClass('table table-bordered table-hover table-striped');
	$(".testlist").css({
		width: '70%'
	}); //because when list of test too long, we need to make the 'execute' button clickable: not covered by the user details card
	$(".testlist").closest('div.bg-blue-lighter').removeClass('bg-blue-lighter'); //we have already added this bg to all 'tcecontentbox', which .testlist falls into the category. However, it feels awful on this design, so we are exempting it

	//give user clue that there is tooltip on acronymns
	$('acronym').addClass('cursor-pointer');

	//fix layout login button
	$("#login").css({
		marginLeft: '-500px'
	})

	//testform
	$("#testform").addClass('border rounded bg-red-lightest pb-4')
	$(".navlink").addClass('pt-4 pl-4')
	$("#terminatetest").addClass('mb-4 ml-4')

	//all bootstrap table that they put td text as centered is bad layout
	$(".table td").css({
		textAlign: 'left'
	});

	//effizy
	$("#userLcd").addClass("animate shake");

	//question list
	$(" ul.question > li").addClass("m-4 p-10 border rounded bg-grey-lightest mb-5")

	//our styling gave "life" to disbaled elements. Fix that:
	//1. remove hover classes
	//2. make bg-grey
	//3. make border darker grey
	$("input[disabled='disabled']").each(function (index, element) {
		let classes = $(element).attr("class");
		if (classes) {
			classes = classes.replace("'", "");
			if (classes) {
				classes = classes.replace('"', "");
				classes = classes.split(" ");
				$.each(classes, function (index, value) {
					//remove hovers
					if (value.indexOf('hover') >= 0) {
						$(element).removeClass(value);
					}
					//remove bg's
					if (value.indexOf('bg-') >= 0) {
						$(element).removeClass(value);
					}
					//remove borders
					if (value.indexOf('border-') >= 0) {
						$(element).removeClass(value);
					}
					//text color
					if (value.indexOf('text-') >= 0) {
						$(element).removeClass(value);
					}
				});
				$(element).addClass('bg-grey-lightest border-grey-light text-grey ' + default_border);
			}
		}
	});

})
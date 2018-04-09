$(document).ready(function() {
	if ($('#live-feed').length !== 0) {
		infoLoadTimeout = setTimeout(function() {
			refreshInfo();
		}, infoLoadDelay);
		
		if (locale !== undefined) {
			moment.locale(locale);
		}
		refreshTimes();
	}
});

var infoLoadTimeout;
var nextRefreshInfoQueued = false;

var infoLoadDelay = infoLoadDelay || 5000;
var infoScrollAnimationDuration = infoScrollAnimationDuration || 500;

function updateLivePage() {
	$.get("/ajax/getLiveCheckins/" + $("#live-content").data('live-type') + "/" + $("#live-content").data('live-id'), { minID: $("#live-feed").children("li").first().data('checkin'), format: "html" } )
	.done(function(data) {
		var append = "";
		var mediaAppend = "";
		$.each(data.checkins, function(key, value) {
    		append += value;
        	$('#no-checkins').hide();
		});
		$.each(data.medias, function(key, value) {
			mediaAppend += value;
		});
		$('.media-number').each(function() {
			var newID = Number($(this).html()) + data.mediaCount;
			$(this).html(newID);
		});
		for (var i = 0; i < data.count; i++) {
			// Remove check-ins if more than 30 are displayed
			if ($("#live-feed").children("li").length > 30) {
				$("#live-feed").children("li").last().remove();
			}
		}
		for (var i = 0; i < data.mediaCount; i++) {
			// Remove photos if more than 35 are displayed
			if ($("#live-media").children("div").length > 35) {
				$("#live-media").children("div").last().remove();
			}
		}
    	$(append).hide().prependTo('#live-feed').css('white-space', 'nowrap').animate({width:'toggle'}, { duration : 500, easing : "swing", complete : function() { $('#live-feed > li').css('white-space', ''); } });
    	$(mediaAppend).hide().prependTo('#live-media').fadeIn();
	});
}

function refreshInfo() {
	$.get("/ajax/getEventInfoMessage/" + $("#live-content").data('live-id'), {} ).done(function(data) {
		infoLoadTimeout = 'PENDING';
		$(".info-line-text").css('right' , '');
		$(".info-line-text").animate({ left: $(".info-line-text").parent().width() }, { duration : infoScrollAnimationDuration, easing : "swing" }).promise().then(
			function() {
				$("#info-line-1").html(data.line1);
				$("#info-line-2").html(data.line2);
				$("#info-line-3").html(data.line3);
				$(".info-line-text").css('left' , '');
				$(".info-line-text").each(function() {
					if ($(this).width() < $(this).parent().width()) {
						$(this).css('right' , $(this).parent().width());
					} else {
						$(this).css('right' , $(this).width());
					}
				});
				$(".info-line-text").animate({ right: '0' }, { duration : infoScrollAnimationDuration, easing : "swing" }).promise().then(
					function () {
						infoLoadTimeout = setTimeout(function() {
							refreshInfo();
						}, infoLoadDelay)
						$(".info-line-text").each(function() {
							if ($(this).width() > $("#info-content").width()) {
								var scrollLength = $("#info-content").width() - $(this).width() - 6;
								var scrollDuration = infoLoadDelay - infoScrollAnimationDuration * 2;
								$(this).animate({ left: scrollLength }, { duration : scrollDuration });
							}
							$(this).find('.animated-increment').each(function () {
								$(this).animateNumber({ number: $(this).data('value') });
							});
						});
					}
				);
			}
		);
	}).fail(function() {
		infoLoadTimeout = setTimeout(function() {
			refreshInfo();
		}, infoLoadDelay)
	});
}

function refreshTimes() {
	$('.checkin-date').each(function() {
		$(this).html("(" + moment($(this).data('date')).fromNow() + ")");
	});
}
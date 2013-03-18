
jQuery(document).ready(function($) {
	$('body').prepend('<div><div id="gallerynav"><div class="prev"></div><div class="close"></div><div class="next"></div></div><div id="galleryplayer"></div><div id="lightbox"></div></div>');
	$('#lightbox').css('height',$('body').height()+'px');
	$('.cellImage img').live("click", function(event) {
		$('#lightbox').css('height',$(document).height()+'px');
		$('#lightbox').fadeIn(500);
		setgalleryimage($(this));
		$('#galleryplayer').show();
		$('#gallerynav').css('top','-'+$('#gallerynav').outerHeight(true)+'px').show().delay(500).animate({top:0});
	});
	$('#gallerybanner, #lightbox').click(function(event) {
		closeGallery();
	});
	$(document).keydown(function(e) { 
	    if (e.keyCode == 27) {
	          closeGallery();
	    } else if (e.keyCode == 39) {
		    navgallery('next');
	    } else if (e.keyCode == 37) {
		    navgallery('prev');
	    }
	});
	function closeGallery() {
		$('#gallerynav .close').css('opacity','.3').animate({'opacity':'1'},200);
		$('#galleryplayer').hide();
		$('#gallerynav').animate({top:'-'+$('#gallerynav').outerHeight(true)+'px'},function(){$(this).hide();});
		$('#lightbox').fadeOut(500);
	}
	function setgalleryimage(imageObj) {
		var image = $(imageObj).attr('src');
		var gallerycontent = '<img src="'+image+'">';
		$('#galleryplayer').html(gallerycontent);
		sizephoto();
	};
	function sizephoto() {
		var wHmax = $(window).height() * .8;
		var wWmax = $(window).width() * .8;
		$('#galleryplayer img').css('max-height',wHmax+'px').css('max-width',wWmax+'px');
		$('#galleryplayer').css('top',(($(window).height() - $('#galleryplayer').height())/2)+'px');
		$('#galleryplayer').css('left',(($(window).width() - $('#galleryplayer').width())/2)+'px');
		
		$('#gallerynav').css('left',(($(window).width() - $('#gallerynav').outerWidth(true))/2)+'px');
	};
	
	$('#galleryplayer, #gallerynav .next').click(function(){
		navgallery('next');
	});
	
	$('#gallerynav .prev').click(function(){
		navgallery('prev');
	});
	
	$('#gallerynav .close').click(function(){
		closeGallery();
	});
	
	function navgallery(direction) {
		var currentimage = $('#galleryplayer').find('img').attr('src');
		var obj = $('.cellImage').find('img[src="'+currentimage+'"]').parent();
		
		if (direction=='prev') {
			$('#gallerynav .prev').css('opacity','.3').animate({'opacity':'1'},200);
			if ($(obj)[0]===$('.cellImage').first()[0]) {
				obj = $('.cellImage').last();
			} else {
				obj = $(obj).prev();
			}
		} else {
			$('#gallerynav .next').css('opacity','.3').animate({'opacity':'1'},200);
			if ($(obj)[0]===$('.cellImage').last()[0]) {
				obj = $('.cellImage').first();
			} else {
				obj = $(obj).next();
			}
		}
		
		setgalleryimage($(obj).find('img'));
	}
});
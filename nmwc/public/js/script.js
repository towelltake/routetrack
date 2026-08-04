/*
 * Function.prototype.bind for IE
 * @see http://webreflection.blogspot.com/2010/02/functionprototypebind.html
 */
if(Function.prototype.bind == null) {

	Function.prototype.bind = ( function(slice) {

		// (C) WebReflection - Mit Style License
		function bind(context) {

			var self = this;
			// "trapped" function reference

			// only if there is more than an argument
			// we are interested into more complex operations
			// this will speed up common bind creation
			// avoiding useless slices over arguments
			if(1 < arguments.length) {
				// extra arguments to send by default
				var $arguments = slice.call(arguments, 1);
				return function() {
					return self.apply(context,
					// thanks @kangax for this suggestion
					arguments.length ?
					// concat arguments with those received
					$arguments.concat(slice.call(arguments)) :
					// send just arguments, no concat, no slice
					$arguments);
				};
			}
			// optimized callback
			return function() {
				// speed up when function is called without arguments
				return arguments.length ? self.apply(context, arguments) : self.call(context);
			};
		}

		// the named function
		return bind;

	}(Array.prototype.slice));
}

/*
 * Peach - Clean & Smooth Admin Template
 * by Stammi <http://themeforest.net/user/Stammi>
 * 
 * ===========
 *   Scripts
 * ===========
 * 
 * -----------------
 * TABLE OF CONTENTS
 * -----------------
 * 
 *  1) Forms
 *  2) Boxes
 *  3) Wizard
 *  4) Page resize
 *  5) Browser hack support
 *  6) Tables
 *  7) Tooltips
 *  8) Navigation
 *  9) Charts
 * 10) Gallery
 * 11) Toolbar buttons
 * 12) jGrowl
 */

(function($) {
	
	
 /* ==================================================
 * 1) Forms
 * ================================================== */
	(function() {
		/*
		 * The sidebar navigation
		 */
		$('aside nav ul.menu').initMenu();
		/*
		 * Form validation
		 */
		if($.fn.validate) {
			$('form.validate').each(function() {
				var validator = $(this).validate({
					ignore : 'input:hidden:not(:checkbox):not(:radio)',
					showErrors : function(errorMap, errorList) {
						this.defaultShowErrors();
						var self = this;
						$.each(errorList, function() {
							var $input = $(this.element);
							var $label = $input.parent().find('label.error').hide();
							if($input.is(':not(:checkbox):not(:radio):not(select)')) {
								$label.addClass('red');
								$label.css('width', '');
								$input.trigger('labeled');
							}
							$label.fadeIn();
						});
					},
					errorPlacement : function(error, element) {
						if(element.is(':not(:checkbox):not(:radio):not(select)')) {
							error.insertAfter(element);
						} else {
							if(element.is('select')) {
								error.appendTo(element.parent());
							} else {
								error.appendTo(element.parent().parent());
							}
						}
					}
				});
				$(this).find('input[type=reset]').click(function(){
					validator.resetForm();
				});
			});
		}
		/*
		 * Custom form elements
		 */
		if($.fn.checkbox) {
			$('input[type="checkbox"]').checkbox({
				cls : 'checkbox',
				empty : 'img/sprites/forms/checkboxes/empty.png'
			});
			$('input:radio').checkbox({
				cls : 'radio-button',
				empty : 'img/sprites/forms/checkboxes/empty.png'
			});
		}
		if ($.fn.chosen) {
			$('select').chosen();
		}
		/*
		 * Placeholders
		 */
		if($.fn.placeholder) {
			$('input, textarea').placeholder();
		}
		/*
		 * Error labels
		 */
		$('input, textarea').bind('labeled', function() {
			/* By Hiren dave
			replace -5 with -10
			*/
			$(this).next().css('width', parseFloat($(this).css('widthExact')) - 5 + "px");
			// $(this).next().outerWidth($(this).width() + 2);
		});
		/* Color input */
		if(!$.browser.opera && $.fn.miniColors) {
			$("input[type=color]").miniColors();
		}
	})();
	
	
 /* ==================================================
 * 2) Boxes
 * ================================================== */
	(function() {
		"use strict";
		/*
		 * Hide the alert boxes
		 */
		$(".alert .hide").click(function() {
			$(this).parent().slideUp();
		});
		/*
		 * Show/hide the boxes
		 */
		$('.box .header > span').click(function() {
			var $this = $(this);
			var $box = $this.parents('.box');
			
			if ($box.find('.content').is(':visible')) {
				$('.content', $box).slideToggle('normal', 'easeInOutCirc', function() {
					$box.toggleClass('closed');
				});
				$('.actions', $box).slideToggle('normal', 'easeInOutCirc');
			} else {
				$('.content', $box).slideToggle('normal', 'easeInOutCirc');
				$('.actions', $box).slideToggle('normal', 'easeInOutCirc');
				$box.toggleClass('closed');
			}
		});
		$('.box .header').each(function() {
			var $this = $(this);
			if(!$this.has('img').length) {
				$this.addClass('no-icon');
			}
		});
		$('.box').each(function(){
			var $this = $(this);
			if ($this.has('.actions').length) {
				$this.find('.content').addClass('with-actions');
			}
			if (!$this.has('.header').length) {
				$this.find('.content').addClass('no-header');
			}
			if ($this.find('.header').hasClass('grey')) {
				$this.find('.content').addClass('grey');
			}
		});
	})();
	
	
 /* ==================================================
 * 3) Wizard
 * ================================================== */
	(function() {
		if($.fn.equalHeights) {
			var showWizPage = function(page_nr, $wiz) {
				var max = $('.steps li', $wiz).length;
				if(page_nr < 1 || page_nr > max) {
					return false;
				} else {
					$('.wizard ul li').removeClass('current');
					$('.wizard ul li').eq(page_nr - 1).addClass('current');

					$wiz.data('step', parseInt(page_nr));
					$('.wiz_page', $wiz).stop(true, true).hide('fade');
					$('.step_' + page_nr, $wiz).stop(true, true).delay(400).show('fade');
					return false;
				}
			};
			var btnClick = function(el, dir) {
				var $wiz = $(el).parents('.wizard');
				var step = $wiz.data('step');
				showWizPage(step + dir, $wiz);
			};
			$('.wizard ul a').click(function() {
				var step = $(this).attr('href').replace('#step_', '');
				var $wiz = $(this).parents('.wizard');
				showWizPage(step, $wiz);
				return false;
			});
			$('.wizard .prev').click(function() {
				btnClick(this, -1);
				return false;
			});
			$('.wizard .next').click(function() {
				btnClick(this, 1);
				return false;
			});
			$('.wizard .wiz_page').equalHeights().not(':first').hide();
			$('.wizard').each(function() {
				var $this = $(this);
				$('.content', $this).height($('.steps', $this).height() + $('.step_1', $this).height());
			});
		}
	})();
	

/* ==================================================
 * 4) Page resize: Resize the #content-wrapper and the sidebar to fill the page
 * ================================================== */
	(function() {
		// http://stackoverflow.com/questions/7785691/using-javascript-to-resize-a-div-to-screen-height-causes-flickering-when-shrinki
		if($('aside').length) {
			$('#content-wrapper').css('margin-bottom', '0');
			var resizeContentWrapper = function() {
				var self = resizeContentWrapper;
				if( typeof self.height == 'undefined') {
					self.height = $(window).height();
				}

				var target = {
					content : $('#content-wrapper'),
					header : $('header'),
					footer : $('footer'),
					sidebar : $('aside')
				};

				var height = {
					window : $(window).height(),
					document : $(document).height(),
					header : target.header.height(),
					footer : target.footer.height()
				};
				var resizeDirection = self.height - height.window;
				self.height = $(window).height();

				var diff = height.header + height.footer + 1;

				$.extend(height, {
					document : $(document).height(),
					window : $(window).height()
				});

				// Check if content without custom height exeeds the window height
				if(resizeDirection >= 0) {
					target.content.css('height', '');
					target.sidebar.css('height', '');
				}

				$.extend(height, {
					document : $(document).height(),
					window : $(window).height()
				});

				if(target.content.height() + diff < height.window) {
					// Set the new content height
					height.content = height.document - diff;
					target.content.css('height', height.content);
				}
			}
			resizeContentWrapper();
			$(window).bind('resize orientationchange', resizeContentWrapper);
			$(document).resize(resizeContentWrapper);
			
			if ($.resize) {
				$.resize.delay = 100;
				$.resize.throttleWindow = false;
			}
		}
	})();
	
	
/* ==================================================
 * 5) Browser hack support
 * ================================================== */
	if($.browser.msie) {
		$('html').addClass('ie');
	} else if ($.browser.opera) {
		$('html').addClass('opera');
	} else if ($.browser.webkit) {
		$('html').addClass('webkit');
	}

	
/* ==================================================
 * 6) Tables
 * ================================================== */
	(function() {
		if($.fn.dataTable) {
			$(document).data('datatables', $.fn.dataTable);
			$.fn.dataTable = function(options) {
				$(document).data('datatables').bind(this, options)().parent().find('select').chosen().next().find('input').remove();
				return $(this);
			}
		}
	})();
	
	
/* ==================================================
 * 7) Tooltips
 * ================================================== */
	(function() {
		if($.fn.tipsy) {
			$('a[rel=tooltip]').tipsy({
				fade : true
			});
			$('a[rel=tooltip-bottom]').tipsy({
				fade : true
			});
			$('a[rel=tooltip-right]').tipsy({
				fade : true,
				gravity : 'w'
			});
			$('a[rel=tooltip-top]').tipsy({
				fade : true,
				gravity : 's'
			});
			$('a[rel=tooltip-left]').tipsy({
				fade : true,
				gravity : 'e'
			});

			$('a[rel=tooltip-html]').tipsy({
				fade : true,
				html : true
			});

			$('div[rel=tooltip]').tipsy({
				fade : true
			});
		}
	})();
	
	
/* ==================================================
 * 8) Navigation
 * ================================================== */
	(function() {
		// Hide all subnavigation
		$('#nav_main > li > ul').hide();
		// Show current subnavigation
		$('#nav_main > li.current').children("ul").show();
		try {
			var $img = $('#nav_main > li.current img');
			$img.attr('src', $img.attr('src').replace('dark', 'blue'));
		} catch(e) {};
		
		$('#nav_main > li a[href="#"]').click(function() {
			// Remove .current class from all tabs
			try {
				var $img = $(this).parent().siblings('.current').find('a > img');
				$img.attr('src', $img.attr('src').replace('blue', 'dark'));
			} catch(e) {};

			$(this).parent().siblings().removeClass('current');
			// Add class .current
			$(this).parent().addClass('current');
			try {
				$img = $(this).find('> img');
				$img.attr('src', $img.attr('src').replace('dark', 'blue'));
			} catch(e){}
			
			// Hide all subnavigation
			$(this).parent().siblings().children("ul").fadeOut(150);
			
			// Show current subnavigation
			if($(this).parent().has("ul").length) {
				$(this).parent().children("ul").fadeIn(150)
			}
			
			return false;
		});
	})();
	
	
/* ==================================================
 * 9) Charts
 * ================================================== */
	$('.graph').bind("plothover", function(event, pos, item) {
		if(item) {
			var x = item.datapoint[0].toFixed(2), y = item.datapoint[1].toFixed(2);
			$(this).tipsy({
				fallback : '',
				followMouse : true,
				autoGravity : true
			});
			$(this).tipsy('setTitle', item.series.label + " is " + y + " at " + x);
			$(this).tipsy('show');
		} else {
			$(this).tipsy('hide');
		}
	});
	
	
/* ==================================================
 * 10) Gallery
 * ================================================== */
	(function() {
		if($.fn.prettyPhoto) {
			$('.gallery .action-list').hide();
			$('.gallery > li').mouseenter(function() {
				$(this).find('.action-list').animate({
					width : "show"
				}, 250);
			});
			$('.gallery > li').mouseleave(function() {
				$(this).find('.action-list').animate({
					width : "hide"
				}, 250);
			});
			$(".gallery a[rel^='prettyPhoto']").prettyPhoto();
		}
	})();
	
	
/* ==================================================
 * 11) Toolbar buttons
 * ================================================== */
	(function() {
		var noPropagation = function(e) {
			e.stopPropagation();
		};
		$(document).click(function() {
			$('.toolbox:visible').fadeOut();
			$('.toolbar_large .dropdown:visible').each(function() {
				$(this).slideUp({
					easing : 'easeInOutCirc'
				});
				$('.toolcaption', $(this).parent()).removeClass('active');
			});
		});
		$('.toolbutton').each(function() {
			var $b = $(this);
			if($b.next().hasClass('toolbox')) {
				$b.click(function(e) {
					noPropagation(e);
					$(this).next().fadeToggle();
				});
				$b.next().click(noPropagation);
				$b.next().hide();
			}

		});
		/*
		 * The toolbar menu
		 */
		$('.toolbar_large').each(function() {
			var $toolbar = $(this);
			$('.toolcaption', $toolbar).click(function(e) {
				$('.dropdown', $toolbar).css('width', parseFloat($('.toolcaption', $toolbar).css('widthExact')) + 2 + "px");
			
				noPropagation(e);
				$(this).toggleClass('active');
				$('.dropdown', $toolbar).slideToggle({
					easing : 'easeInOutCirc'
				});
				$('.dropdown', $toolbar).click(noPropagation);
			});
			$('.dropdown', $toolbar).hide();
		});
	})();
	
	
/* ==================================================
 * 12) jGrowl
 * ================================================== */
	 if ($.jGrowl) {
		 $.jGrowl.defaults.life = 8000
		 $.jGrowl.defaults.pool = 5
	 }
})(jQuery);

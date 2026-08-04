// usage: log('inside coolFunc', this, arguments);
// paulirish.com/2009/log-a-lightweight-wrapper-for-consolelog/
window.log = function() {
	log.history = log.history || [];
	// store logs to an array for reference
	log.history.push(arguments);
	if(this.console) {
		arguments.callee = arguments.callee.caller;
		var newarr = [].slice.call(arguments); ( typeof console.log === 'object' ? log.apply.call(console.log, console, newarr) : console.log.apply(console, newarr));
	}
};
// make it safe to use console.log always
(function(b) {
	function c() {
	}

	for(var d = "assert,clear,count,debug,dir,dirxml,error,exception,firebug,group,groupCollapsed,groupEnd,info,log,memoryProfile,memoryProfileEnd,profile,profileEnd,table,time,timeEnd,timeStamp,trace,warn".split(","), a; a = d.pop(); ) {
		b[a] = b[a] || c
	}
})((function() {
	try {console.log();
		return window.console;
	} catch(err) {
		return window.console = {};
	}
})());

/*
 * Peach - Clean & Smooth Admin Template
 * by Stammi <http://themeforest.net/user/Stammi>
 *
 * ===========
 *   Plugins
 * ===========
 *
 * -----------------
 * TABLE OF CONTENTS
 * -----------------
 *
 * 1) Menu
 * 2) Alert Boxes
 *    a) Create
 *	  b) Remove
 * 3) Tabs
 * 4) CSS height/width hook
 */

/* ==================================================
 * 1) Menu by Simon Stamm
 * ================================================== */
jQuery.fn.initMenu = function() {
	$(this).parent().height($(this).height() + 10);
	return this.each(function() {
		var theMenu = $(this).get(0);

		$('li:has(ul)',this).each(function() {
			$('>a', this).append("<span class='arrow'>&raquo;</span>");
		});
		$('.sub', this).hide();
		$('li.expand > .sub', this).show();
		$('li.expand > .sub', this).prev().addClass('active');
		$('li a', this).click(function(e) {
			e.stopImmediatePropagation();
			var theElement = $(this).next();
			var parent = this.parentNode.parentNode;
			if($(this).hasClass('active-icon')) {
				$(this).addClass('non-active-icon');
				$(this).removeClass('active-icon');
			} else {
				$(this).addClass('active-icon');
				$(this).removeClass('non-active-icon');
			}
			if($(parent).hasClass('noaccordion')) {
				if(theElement[0] === undefined) {
					window.location.href = this.href;
				}
				$(theElement).slideToggle('normal', function() {
					if($(this).is(':visible')) {
						$(this).prev().addClass('active');
					} else {
						$(this).prev().removeClass('active');
						$(this).prev().removeClass('active-icon');
					}
				});
				return false;
			} else {
				// Using accordeon
				if(theElement.hasClass('sub') && theElement.is(':visible')) {
					// If already visible, slide up
					if($(parent).hasClass('collapsible')) {
						$('.sub:visible', parent).first().slideUp('normal', function() {
							$(this).prev().removeClass('active');
							$(this).prev().removeClass('active-icon');
						});
						return false;
					}
					return false;
				} else if(theElement.hasClass('sub') && !theElement.is(':visible')) {
					// If not visible, slide down
					$('.sub:visible', parent).first().slideUp('normal', function() {
						$(this).prev().removeClass('active');
						$(this).prev().removeClass('active-icon');
					});
					theElement.slideDown('normal', function() {
						$(this).prev().addClass('active');
					});
					return false;
				}
			}
		});
	});
};
/* ==================================================
 * 2) Alert Boxes by Simon Stamm
 * ================================================== */

/* ==================================================
 * 2a) Alert Boxes: Create
 * ================================================== */
(function($) {
	$.fn.alertBox = function(message, options) {
		var settings = $.extend({}, $.fn.alertBox.defaults, options);

		this.each(function(i) {
			var block = $(this);

			var alertClass = 'alert ' + settings.type;
			if(settings.noMargin) {
				alertClass += ' no-margin';
			}
			if(settings.position) {
				alertClass += ' ' + settings.position;
			}
			var alertMessage = $('<div style="display:none" class="' + alertClass + ' .generated">' + message + '</div>');
			if (settings.icon) {
				alertMessage.prepend($('<span>').addClass('icon'));
			}

			var alertElement = block.prepend(alertMessage);

			$(alertMessage).fadeIn();
		});
	};
	// Default config for the alertBox function
	$.fn.alertBox.defaults = {
		type : 'info',
		position : 'top',
		noMargin : true,
		icon: false
	};
})(jQuery);

/* ==================================================
 * 2b) Alert Boxes: Remove
 * ================================================== */

(function($) {
	$.fn.removeAlertBoxes = function() {
		var block = $(this);

		var alertMessages = block.find('.alert');
		alertMessages.remove();
	};
})(jQuery);

/* ==================================================
 * 3) Tabs by Simon Stamm
 * ================================================== */

(function($) {
	$.fn.createTabs = function() {
		var container = $(this);

		container.find('.tab-content').hide();
		container.find(".header ul li:first").addClass("current").show();
		container.find(".tab-content:first").show();

		container.find(".header ul li").click(function() {

			container.find(".header ul li").removeClass("current");
			$(this).addClass("current");
			container.find(".tab-content").hide();

			var activeTab = $(this).find("a").attr("href");
			$(activeTab).fadeIn();
			return false;
		});
		
		return container;
	};
})(jQuery);

/* ==================================================
 * 4) CSS height/width hook
 * ================================================== */

/*
 *
 * Because jQuery rounds the values :-/
 */
(function($) {
	if ($.browser.msie) {
		if (!window.getComputedStyle) {
			window.getComputedStyle = function(el, pseudo) {
				this.el = el;
				this.getPropertyValue = function(prop) {
					var re = /(\-([a-z]){1})/g;
					if (prop == 'float') prop = 'styleFloat';
					if (re.test(prop)) {
						prop = prop.replace(re, function () {
							return arguments[2].toUpperCase();
						});
					}
					return el.currentStyle[prop] ? el.currentStyle[prop] : null;
				}
				return this;
			}
		}
	}

	var dir = ['height', 'width'];
	$.each(dir, function() {
		var self = this;
		$.cssHooks[this + 'Exact'] = {
			get : function(elem, computed, extra) {
				return window.getComputedStyle(elem)[self];
			}
		};
	});
})(jQuery);
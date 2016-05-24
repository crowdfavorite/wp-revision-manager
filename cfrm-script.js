/**
 * ColumnizeLists
 * @version 0.1
 * @requires jQuery
 * Copyright 2010, Crowd Favorite
 *
 * Break lists into chunks so you can build top-to-bottom-left-to-right columned lists
 * Usage: $('ul.my-columnizer-class').columnizeLists({});
 */
(function($) {

	$.fn.columnizeLists = function(args) {
		// Merge our default args and the user's args
		var args = $.extend({}, $.fn.columnizeLists.args, args);

		// Loop through every target
		this.each(function(){
			var $this = $(this);

			var cont = $this.parent();
			var items = $this.find('li');
			var items_count = items.size();

			if (args.preserveOriginalClass) {
				var originalClass = ' class="' + $this.attr('class') + '"';
			} else {
				var originalClass = '';
			};

			// If we have the column preference, figure out how many rows we should have, then do rows
			if (args.pref == 'cols') {
				var rem = items_count % args.cols;
				args.rows = Math.floor(items_count / args.cols);
				rem ? extra = 1 : extra = 0;
			}

			// Put a wrapper around our new divs we're creating
			cont.append('<div class="' + args.divWrapperClass + '"></div>');
			// Find classes as compound class selectors
			var div_wrapper = cont.find('.' + args.divWrapperClass.replace(' ', '.'));

			// Loop through each list item
			var i = 0;
			var col_num = 0;
			items.each(function() {
				// fancy-pants math to see if we should append an extra row till we have no remainder
				(extra && col_num <= rem) ? row_count = args.rows + extra : row_count = args.rows;
				if (i % row_count == 0) {
					col_num++;
					i = 0;
					var colClasses = args.colClass.replace(' ', '-');
					cur_col = colClasses + '-' + col_num.toString();
					div_wrapper.append('<div class="' + args.colClass + ' ' + cur_col + '"><ul' + originalClass + '></ul></div>');
				}

				$(this).appendTo(div_wrapper.find('.' + cur_col + ' ul'));
				i++;
			});

			// Now add the container class, and remove the initial ul
			if (args.containerClass) {
				cont.addClass(args.containerClass)
			};
			cont.children("ul").remove();
		});

	};

	/**
	 * Default settings
	 */
	$.fn.columnizeLists.args = {
		pref: 'cols',
		rows: 10,
		cols: 4,
		containerClass: 'clearfix',
		colClass: 'cf-col',
		divWrapperClass: 'div-wrapper',
		preserveOriginalClass: false
	}
})(jQuery);
jQuery(function($) {
	$('#cfr_revision_manager_keys, #cfr_revision_manager_keys_required').columnizeLists({
		'cols': 3
	});
});

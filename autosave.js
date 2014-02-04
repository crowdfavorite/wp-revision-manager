(function($){
	wp.autosave.getPostData = function() {
		var meta = {};
		$(cfrm.selector).each(function(i, element) {
			meta[cfrm.prefix + element.name] = $(element).val();
		})

		var ed = typeof tinymce != 'undefined' ? tinymce.activeEditor : null, post_name, parent_id, cats = [], data;

		data = {
			action: 'autosave',
			autosave: true,
			post_id: $('#post_ID').val() || 0,
			autosavenonce: $('#autosavenonce').val() || '',
			post_type: $('#post_type').val() || '',
			post_author: $('#post_author').val() || '',
			excerpt: $('#excerpt').val() || '',
			cfrm_meta : meta || ''
		};

		if ( ed && !ed.isHidden() ) {
			// Don't run while the tinymce spellcheck is on. It resets all found words.
			if ( ed.plugins.spellchecker && ed.plugins.spellchecker.active ) {
				data.autosave = false;
				return data;
			} else {
				if ( 'mce_fullscreen' == ed.id )
					tinymce.get('content').setContent(ed.getContent({format : 'raw'}), {format : 'raw'});

				tinymce.triggerSave();
			}
		}

		if ( typeof fullscreen != 'undefined' && fullscreen.settings.visible ) {
			data.post_title = $('#wp-fullscreen-title').val() || '';
			data.content = $('#wp_mce_fullscreen').val() || '';
		} else {
			data.post_title = $('#title').val() || '';
			data.content = $('#content').val() || '';
		}

		/*
		// We haven't been saving tags with autosave since 2.8... Start again?
		$('.the-tags').each( function() {
			data[this.name] = this.value;
		});
		*/

		$('input[id^="in-category-"]:checked').each( function() {
			cats.push(this.value);
		});
		data.catslist = cats.join(',');

		if ( post_name = $('#post_name').val() )
			data.post_name = post_name;

		if ( parent_id = $('#parent_id').val() )
			data.parent_id = parent_id;

		if ( $('#comment_status').prop('checked') )
			data.comment_status = 'open';

		if ( $('#ping_status').prop('checked') )
			data.ping_status = 'open';

		if ( $('#auto_draft').val() == '1' )
			data.auto_draft = '1';

		return data;
	}
})(jQuery);

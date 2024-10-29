function uni_soc_rep_commentsvote_add(comment_id,nonce,alignment) {

    jQuery.ajax({
        type: 'POST',
        url: uni_soc_rep_votecommentajax.ajaxurl,
        data: {
            action: 'uni_soc_rep_commentsvote_ajaxhandler',
            commentid: comment_id,
            nonce: nonce,
			alignment: alignment
        },
        success: function(data, textStatus, XMLHttpRequest) {
            var linkofcomment = '#commentsvote-' + comment_id;
            jQuery(linkofcomment).html('');
            jQuery(linkofcomment).append(data);
        },
        error: function(XMLHttpRequest, textStatus, errorThrown) {
            alert(errorThrown);
        }
    });


}



(function ($) {
	$(document).ready(function () {
		$("#zone-posts-area").sortable({
			placeholder: "ui-state-highlight"
		});
		$("#zone-posts-area").disableSelection();

		function updateListIndex (){
			$('#zone-posts-area li.zone-post').each(function(i){
				i = Number (i + 1);
				if( $(this).find('.number').length ){
					$(this).find('.number').text( i );
				}else{
					$(this).prepend('<span class="number">'+ i +'.</span>')
				}
			})
		};
		updateListIndex();

		$('#zoninator_organizer_zone_id').on('change', function () {
			var thisvalue = parseInt(this.value);
			if (thisvalue != '' && !isNaN(thisvalue)) {
				$('#zoninator_organizer_zone_box-list, .add-post-box').removeClass('hidden');
				$('.select-sumthing').addClass('hidden');
				$.ajax({
					type: "post",
					dataType: "json",
					url: zoScripts.ajaxUrl,
					data: {action: "get_posts_in_zone", zone_id: thisvalue},
					success: function (response) {
						if (response.type == "success") {
							postsObj = response.objects;
							$('#zone-posts-area').find('.zone-post').remove();
							for (var key in postsObj) {
								var objRow = '<li id="zone-post-' + postsObj[key].ID + '" class="zone-post ui-sortable-handle ui-state-default" data-post-id="' + postsObj[key].ID + '">'+ postsObj[key].post_title +'<div class="row-actions">';
                                objRow += '<a href="'+ zoScripts.adminUrl +'/wp-admin/post.php?post='+ postsObj[key].ID +'&amp;action=edit" class="edit" target="_blank" title="Opens in new window">Editar</a> |';
                                objRow += '<a href="#" class="delete" title="Remove this item from the zone">Remover</a> |';
                                objRow += '<a href="'+ zoScripts.postPermalink +'" class="view" target="_blank" title="Opens in new window">See post</a>';
								objRow += '</div></li>';
								$('#zone-posts-area').append(objRow);
								updateListIndex();
                                deletPostFromZone();
							}
						}
						else {
							console.log("Error...")
						}
					}
				});
			} else {
				$('#zoninator_organizer_zone_box-list, .add-post-box').addClass('hidden');
				$('.select-sumthing').removeClass('hidden');
			}
		});

		var compareArrays = function (arr1, arr2, sort) {
			if (arr1.length != arr2.length) return false;

			if (sort) {
				arr1 = arr1.sort(),
						arr2 = arr2.sort();
			}
			for (var i = 0; arr2[i]; i++) {
				if (arr1[i] !== arr2[i]) {
					return false;
				}
			}
			return true;
		};

		var getZonePostIds = function () {
			var ids = [],
					$posts = '';
			$('#zone-posts-area').find('.zone-post').each(function (i, elem) {
				ids.push($(elem).data('post-id'));
			});
			return ids;
		}

		$("#zone-posts-area").on('sort sortstart sortchange sortupdate', function (event, ui) {
			updateListIndex();
		});

		$("#zone-posts-area").on("sortstop", function (event, ui) {
			updateListIndex();
			var zoneId = $('#zoninator_organizer_zone_id').find("option:selected").val(),
					postIds = getZonePostIds();

			// console.log('IDs', getZonePostIds());
			$.ajax({
				type: "post",
				dataType: "json",
				url: zoScripts.ajaxUrl,
				data: {action: "reorder_posts_in_zone", zoneId: zoneId, postIds: postIds},
				success: function (response) {
					if (response.type == "success") {
						// console.log(response);
					}
					else {
						console.log("Error...")
					}
				}
			});
		});

		$('.add-post-to-zone').on('click', function (e) {
			e.preventDefault();
			// console.log( zoScripts );
			var postData = $(this).data('post-id'),
					postName = zoScripts.string__postTitle,
					postID = zoScripts.thePostID,
					/*<div class="row-actions">
						<a href="http://ppm.local/wp-admin/post.php?post=1513&amp;action=edit" class="edit" target="_blank" title="Opens in new window">Editar</a> |
						<a href="#" class="delete" title="Remove this item from the zone">Remover</a> |
						<a href="http://ppm.local/?post_type=post&amp;p=1513" class="view" target="_blank" title="Opens in new window">See post</a>
					</div>*/
					postList = $('#zone-posts-area'),
					postRow  = '<li id="zone-post-' + postData + '" class="zone-post ui-sortable-handle ui-state-default" data-post-id="' + postData + '">' + postName + '<div class="row-actions">';
					postRow += '<a href="'+ zoScripts.adminUrl +'/wp-admin/post.php?post='+ postID +'&amp;action=edit" class="edit" target="_blank" title="Opens in new window">Editar</a> |';
					postRow += '<a href="#" class="delete" title="Remove this item from the zone">Remover</a> |';
					postRow += '<a href="'+ zoScripts.postPermalink +'" class="view" target="_blank" title="Opens in new window">See post</a>';
					postRow += '</div></li>';

				console.log( postID );

			if (postList.find("[data-post-id='" + postData + "']").length) {
				var infoText = $('.add-post-box > p').text();
				$('.add-post-box > p').addClass('error').text( zoScripts.string__postOnTheList );
				postList.find("[data-post-id='" + postData + "']").addClass('already-on-list');
				setTimeout(function () {
					$('.add-post-box > p').removeClass('error').text(infoText);
					postList.find("[data-post-id='" + postData + "']").removeClass('already-on-list');
				}, 2000);
			} else {
				postList.prepend(postRow);

				var zoneId = $('#zoninator_organizer_zone_id').find("option:selected").val(),
						postIds = getZonePostIds();

				$.ajax({
					type: "post",
					dataType: "json",
					url: zoScripts.ajaxUrl,
					data: {action: "reorder_posts_in_zone", zoneId: zoneId, postIds: postIds},
					success: function (response) {
						if (response.type == "success") {
							// console.log(response);
						}
						else {
							console.log("Error...")
						}
					}
				});

				updateListIndex();
                deletPostFromZone();
			}
		});

		function deletPostFromZone(){
            $('#zone-posts-area .delete').on('click', function (e) {
                e.preventDefault();
                var thisLI = $(this).closest('.zone-post'),
                    zoneId = $('#zoninator_organizer_zone_id').find("option:selected").val(),
                    postID = $(this).closest('.zone-post').data('post-id');

                // console.log('zoneId: ' + zoneId, ' postID: ' + postID);
                $.ajax({
                    type: "post",
                    dataType: "json",
                    url: zoScripts.ajaxUrl,
                    data: {action: "delete_post_in_zone", zoneId: zoneId, postID: postID},
                    success: function (response) {
                        if (response.type == "success") {
                            thisLI.hide('slow').remove();
                            updateListIndex();
                        }
                        else {
                            console.log("Error...")
                        }
                    }
                });
            });
        }
        deletPostFromZone();

	})
})(jQuery);

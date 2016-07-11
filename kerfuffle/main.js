/*
 * Kerfuffle by Bryan Potts <pottspotts@gmail.com>, 06/01/16
 * Total rewrites so far: 1
 *
 * TOPICS are name-only elements with a subset of nestable comments.
 * COMMENTS have a topic_id, and can have a parent_id IF they are a nested child.
 * 'locator_id' is used to map topics to section/group/page IDs
 *
 */
$(document).ready(function(){

  (function messageBoard(){

    /**
     * Request a single topic via url, OR show all topics for this page/section/group
     * @param {Number} tid - Topic ID
     * @return void
     */
    function getTopics(tid){ // optional topic_id (tid)

      var params = undefined;

      // Check URL for a current topic_id, i.e. we're viewing a topic
      if($.qs["mbt"] !== undefined){

        // Show comments
        updateComments();

        // If a topic is clicked on, expect a tid and override the mbt
        if(tid) $.qs["mbt"] = tid;

        // Configure params and make API call
        params = {"method":"getTopics","params":{"topics":[$.qs["mbt"]]}};
        $.post('api.php',params,function(r){ fillTopics(r) });

        // Show the reply box, as we're on a topic page
        $('#msgb-comment-reply').show();

      // if no topic set in url, show all topics for this page/seciton/group
      } else {

        // Check to make sure we have valid url data before making an API call
        var pT = pageType();
        if(pT.type && pT.id) {
          params = {"method":"getTopics","params":{"locator_id":pT.id,"locator_type": pT.type}};
          $.post('api.php',params,function(v){ fillTopics(v) });

        // Show Create New Topic form
        $('#msgb-new-topic').show();

        // OR return an error message if we have bad url data
        } else {
          console.log("Error: lid or lidT");
        }
      }
    }

    // Insert topics returned from API into the DOM
    function fillTopics(Ts){
      console.log("fillTopics()");
      _.each(Ts,function(o,i){
        console.log("next topic to show:");
        console.log(o);

        $T = $('#msgb-topics-template').clone().removeAttr("id").attr("data-id", o.id);
        $T.find('.msgb-topic-name').html(o.name);
        $T.find('.msgb-topic-date').attr('datetime',o.date_create);
        $('#msgb-topics').append($T);
      });
      $('time.timeago').timeago();
    }

    // Call API to get comments for the current topic_id (mbt)
    // if an ID is passed, we're looking to append only that comment
    function updateComments(id){

      // Show comments box
      $('#msgb-comments').show();

      // only load comments if a topic is picked
      if($.qs["mbt"] !== undefined){
        p = {"method":"updateComments","params":{"topic_id":$.qs["mbt"],"comment_id":id}};
        $.post('api.php',p,function(v){ fillComments(v) }); // ajax and call fill
      }

      // Insert comments from API into DOM
      function fillComments(o){
        console.log("Filling comments:");
        _.each(o,function(o,i){
          console.log(o);
          $T = $('#msgb-comment-template').clone().removeAttr("id").attr("data-id", o.id);
          $T.find('.msgb-comment-body').html(o.content);
          $T.find('.msgb-comment-header').html("Bryan Potts,<time ");
          $('#msgb-comments').append($T);
        });
        $('.msgb-delete-comment').on("click", function(){ deleteComment($(this).parent().parent(".msgb-comment")) });
      }
    }

    // Save new Comments/Replies
    function saveComment(){
      p = {"method":"newComment","params":{"topic_id":$.qs["mbt"], "content": CKEDITOR.instances.msgb_reply_text.getData() }};
      console.log("comment params:");
      console.log(p);
      $.post('api.php',p,function(v){
        if(v.status=="success") updateComments(v.comment_id);
        CKEDITOR.instances.msgb_reply_text.setData('');
      });
    }

    // Catch topic click, update url, refresh
    $("#msgb-topics").on("click","div",function(){
      refresh("mbt",$(this).attr("data-id"));
    });

    // Show All button clicked
    $("#msgb-ctrl-all").click(function(){ refresh("mbt",0,"delete")}); // remove the topic_id from url to see all

    // Comment, Reply button clicked
    $('#msgb-send-reply').click(function(){ saveComment(); });

    // Delete Comment
    function deleteComment(o){
      p = {"method":"deleteComment","params":{ "comment_id": o.attr('data-id') }};
      $.post('api.php',p,function(v){ if(v.status=="success") o.remove(); });
    }

    // Save New Topic
    $('#msgb-topic-save').click(function(){
      console.log("saving new topic... ID: " + $.qs['section_id']);
      p = {
        "method":"newTopic",
        "params": {
          "name": $('#msgb-new-topic-name').val(),
          "locator_id": $.qs['section_id'], // TODO: getLocatorID(),
          "locator_type": 'section_id',     // TODO: getLocatorType(),
          "comment": $('#msgb-new-topic-comment').val()
        }
      };
      $.post('api.php',p,function(v){ if(v.status=="success") console.log(v.topic); fillTopics([v.topic]) });
    });

    // initialize initial view (show all topics, get comments), starting with finding the page type
    if(pageType() !== false){ getTopics(); }

    // Initialize CKEditor
    CKEDITOR.replace( 'msgb_reply_text', {toolbar: [
      { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ], items: [ 'Bold', 'Italic', 'Underline', 'Strike' ] },
      { name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align', 'bidi' ], items: [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'BidiLtr', 'BidiRtl', 'Language' ] },
      { name: 'links', items: [ 'Link', 'Unlink'] },
      { name: 'insert', items: [ 'Image', 'Table' ] }
    ]} );

    /* Helper functions */

    // Get page type (section ID, page ID, etc)
    function pageType(){
      types = ['section_id','page_id','group_id'];
      var match= false, pT = false;
      _.each(types, function(qv,qk){
        match = $.qs[qv] !== undefined ? $.qs[qv] : undefined;
        if(match) pT = {"type":qv,"id":match}
      });
      if(pT){ return pT; } else { return false }
    }

  })();

});




/* Utility functions - You probably don't want to edit these */

(function($) {
  $.qs = (function(a) {
    if (a == "") return {};
    var b = {};
    for (var i = 0; i < a.length; ++i)
    {
      var p=a[i].split('=');
      if (p.length != 2) continue;
      b[p[0]] = decodeURIComponent(p[1].replace(/\+/g, " "));
    }
    return b;
  })(window.location.search.substr(1).split('&'))
})(jQuery);

// compile querystring for appending/updating
function refresh(n,v,act){
  loc = window.location+""; // convert to string
  qs = "?";
  var update = false;
  _.each($.qs,function(va,k){ // iterate over the QS params
    if(k==n) { // don't update if there is no change to the URL
      va = v; update = true;
    }
    if(act=="delete" && n==k) {} else { qs+="&"+k+"="+va; } // delete the var if this is a delete act
  });
  if(!update && act !== "delete") qs+= "&"+n+"="+v;
  window.location.replace(loc.split("?")[0]+qs); // kick off page refresh with updated params
}

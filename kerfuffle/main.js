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
  (function messageBoard(){ // closure to limit scope

    // Request single topic from url or show all for this page/section/group; called implicitly
    function getTopics(tid){ // optional topic_id (tid)

      var p, uP, lidT, lid = undefined;

      // Check URL for a current topic_id (mbt)
      if($.qs["mbt"] !== undefined){

        // If a topic is clicked on, expect a tid and override the mbt
        if(tid) $.qs["mbt"] = tid;

        // Configure params and make API call
        p = {"method":"getTopics","params":{"topics":[$.qs["mbt"]]}};
        $.post('api.php',p,function(r){ fillTopics(r) });

        // Show the reply box, as we're on a topic page
        $('#msgb-comment-reply').show();

      // if no topic set in url, show all topics for this page/seciton/group
      } else {

        // check for these URL types to use as locator type and ID
        uP = ['section_id','group_id'];

        // Find and set locator ID, type
        _.each(uP,function(v){ if($.qs[v] !== undefined) {
          lid = parseInt($.qs[v]); // url params are strings by default
          lidT = v; // e.g. "section_id" from url
        }});

        // Check to make sure we have valid url data before making an API call
        if(lid && lidT) {
          p = {"method":"getTopics","params":{"locator_id":lid,"locator_type": lidT}};
          $.post('api.php',p,function(v){ fillTopics(v) }); // ajax and call fill

        // OR return an error message if we have bad url data
        } else {
          console.log("Error: lid or lidT");
        }
      }

      // Insert topics returned from API into the DOM
      function fillTopics(Ts){
        _.each(Ts,function(o,i){
          //console.log(o);
          $('#msgb-topics').append($('<div data-id="'+o.id+'"><a href="#">'+o.name+'</a></div>'));
        })
      }
    }

    // Call API to get comments for the current topic_id (mbt)
    // if an ID is passed, we're looking to append only that comment
    function getComments(id){
      console.log("getComments");

      // only load comments if a topic is picked
      if($.qs["mbt"] !== undefined){
        p = {"method":"getComments","params":{"topic_id":$.qs["mbt"],"comment_id":id}}; // create API params
        $.post('api.php',p,function(v){ console.log(v); fillComments(v) }); // ajax and call fill
      }

      // Insert comments from API into DOM
      function fillComments(o){
        _.each(o,function(o,i){
          $('#msgb-comments').append('<div data-id="'+o.id+'">'+o.content+'</div>');
        })
      }
    }

    // Save new Comments/Replies
    function saveComment(){
      p = {"method":"newComment","params":{"topic_id":$.qs["mbt"], "content": $('#msgb-reply-text').val() }};
      $.post('api.php',p,function(v){
        console.log(v);
        if(v.status=="success") getComments(v.comment_id);
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

    // initialize initial view (show all topics, get comments)
    getTopics(); getComments();

  })();

});




/* Utility functions - Use caution when editing or using these */

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
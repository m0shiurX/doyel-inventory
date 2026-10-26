$(document).ready(function() {
   $('.autosuggest').keyup (function(){

       var search_term = $(this).attr('value');
       $.post('AutoSuggestDropDown/search.php', {search_term:search_term}, function(data){
          $('.result').html(data);

          $('.result td.re').click(function() {
              var result_value = $(this).text();
              $('.autosuggest').attr('value', result_value);
              $('.result').html('');
          });
       });
   });
});

$(document).ready(function() {
   $('.autosuggest').click (function(){

       var search_term = $(this).attr('value');
       $.post('AutoSuggestDropDown/search.php', {search_term:search_term}, function(data){
          $('.result').html(data);

          $('.result td.re').click(function() {
              var result_value = $(this).text();
              $('.autosuggest').attr('value', result_value);
              $('.result').html('');
          });
       });
   });
});

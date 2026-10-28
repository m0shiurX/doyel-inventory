// Keep the report's existing HTML suggestions and support keyboard selection.
jQuery(function($) {
    $('.autosuggest').each(function() {
        var input = $(this);
        var result = input.siblings('.result');
        var selected = -1;
        var request;
        var revision = 0;
        function dismiss() {
            revision++;
            if (request) request.abort();
            result.empty();
            selected = -1;
        }
        function choose(cell) {
            input.val($(cell).text());
            dismiss();
            input.trigger('focus');
        }
        function search() {
            var current = ++revision;
            if (request) request.abort();
            selected = -1;
            request = $.post('AutoSuggestDropDown/search.php', {search_term: input.val()})
                .done(function(data) {
                    if (current === revision) result.html(data);
                });
        }
        input.on('input click', search);
        input.on('keydown', function(event) {
            var cells = result.find('td.re');
            if (event.key === 'Escape') {
                dismiss();
            } else if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && cells.length) {
                event.preventDefault();
                selected = Math.max(0, Math.min(cells.length - 1,
                    selected + (event.key === 'ArrowDown' ? 1 : -1)));
                result.find('tr').removeClass('selected');
                cells.eq(selected).parent().addClass('selected');
            } else if (event.key === 'Enter' && cells.length) {
                event.preventDefault();
                choose(cells.get(selected === -1 ? 0 : selected));
            } else if (event.key === 'Tab') {
                if (selected !== -1) input.val(cells.eq(selected).text());
                dismiss();
            }
        });
        result.on('click', 'td.re', function() { choose(this); });
    });
});

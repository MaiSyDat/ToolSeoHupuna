jQuery(document).ready(function ($) {
    const $searchBtn = $('#hupuna-search-btn');
    const $searchInput = $('#hupuna-search-input');
    const $resultsWrap = $('#hupuna-search-results');
    const $resultsBody = $('#hupuna-search-results-body');

    $searchBtn.on('click', function () {
        const keyword = $searchInput.val().trim();

        if (keyword === '') {
            alert(hupunaKeywordSearch.strings.error);
            return;
        }

        $searchBtn.prop('disabled', true).find('.dashicons').addClass('spin');
        $resultsWrap.slideUp();
        $resultsBody.empty();

        $.ajax({
            url: hupunaKeywordSearch.ajaxUrl,
            type: 'POST',
            data: {
                action: 'hupuna_keyword_search',
                nonce: hupunaKeywordSearch.nonce,
                keyword: keyword
            },
            success: function (response) {
                if (response.success) {
                    const results = response.data.results;

                    if (results.length > 0) {
                        results.forEach(function (item) {
                            const row = `
                                <tr>
                                    <td><strong>${item.type}</strong></td>
                                    <td>${item.title}</td>
                                    <td><div class="tsh-excerpt">${item.excerpt}</div></td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <a href="${item.edit_link}" class="button button-small" target="_blank">${hupunaKeywordSearch.strings.edit}</a>
                                            <a href="${item.view_link}" class="button button-small" target="_blank">${hupunaKeywordSearch.strings.view}</a>
                                        </div>
                                    </td>
                                </tr>
                            `;
                            $resultsBody.append(row);
                        });
                        $resultsWrap.slideDown();
                    } else {
                        alert(hupunaKeywordSearch.strings.noResults);
                    }
                } else {
                    alert(response.data.message || hupunaKeywordSearch.strings.error);
                }
            },
            error: function () {
                alert(hupunaKeywordSearch.strings.error);
            },
            complete: function () {
                $searchBtn.prop('disabled', false).find('.dashicons').removeClass('spin');
            }
        });
    });

    // Allow pressing Enter to search
    $searchInput.on('keypress', function (e) {
        if (e.which === 13) {
            $searchBtn.click();
        }
    });
});

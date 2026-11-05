$.fn.gridFilter = function(userOptions) {
    let defaultOptions = {
            filterFieldAttribute: 'data-filter-field',
            filterInputAttribute: 'data-filter-for',
            filterContentAttribute: 'data-filter-content',
            filterDataAttributePrefix: 'data-filter-',
            lookInContent: true
        },
        grid = $(this);


    let plugin = {
        init () {
            $.each(userOptions.filters, (key, item) => {
                plugin.setEvents(item);
            });
        },
        setEvents (filterObj) {
            let attribute = filterObj.attribute;

            $(filterObj.input).on('input', function(){
                let searchValue = $(this).val().replace(/\s+/g, '').replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&").toString(),
                    filterRegex = new RegExp(searchValue, 'i'),
                    filterFunc = (row, content) => {
                        if(content.search(filterRegex) === -1){
                            row.css('display', 'none');
                        }else{
                            row.css('display', 'table-row');
                        }
                    };

                $(grid).find('tr[' + defaultOptions.filterDataAttributePrefix + attribute +']').each((key, item) => {
                    filterFunc(
                        $(item),
                        $(item).attr(defaultOptions.filterDataAttributePrefix + attribute).replace(/\s+/g, '')
                    );
                });
            })
        }
    };

    switch(userOptions){
        case undefined:
            console.log('Can\'t run that function without params!');
            return;
        default:
            plugin.init();
            break;
    }


};
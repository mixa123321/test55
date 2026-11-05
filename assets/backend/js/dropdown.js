class Dropdown {
    static hide_all() {
        $('.dropdown_hide').slideUp(300);
    }

    static init() {
        $('html').click(function() {
            Dropdown.hide_all();
        });
    }
}

Dropdown.init();
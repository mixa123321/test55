$(function () {
    $('.js-delete-btn').click(function () {
        $('#deleteDocumentModal').modal('show');
        $('#document_id').val($(this).data('document-id'));
    });

    $('#upload_file').change(({target}) => {
        let selectedFile = null;
        if (target.files.length > 0)
            selectedFile = target.files[0];
        const getFileName = name => {
            let max = 50;
            if (name.length < max)
                return name;
            let split = name.split('.');
            if (split.length === 2)
                return split[0].substr(0, max - 4) + '...' + split[1];
            return name.substr(0, max);
        };
        $('#upload_name').text(selectedFile === null ? 'Select document file' : getFileName(selectedFile.name));
        $('#btn_upload').prop('disabled', selectedFile === null);
    });
});
<div class="modal" tabindex="-1" role="dialog" id="editDoc">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit additional documentation</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body mx-2">
                <div class="row">
                    <div class="col">
                        <p class="form-control-static"><b>Additional Documentation</b></p>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-outline-secondary change_upload"></button>
                    </div>
                </div>
                <div class="form-group row">
                    <p class="form-control-static">All orders are despatched with a despatch note and customs documentation if required.</p>
                    <p class="form-control-static">However, if you have any specific additional documentation for this order you can upload it here
                        <br>(<span class="text-success">Must be in PDF format < 10Mb</span>) and it will be included too.</p>
                    <p class="form-control-static">Please do not upload promotional material.</p>
                </div>
                <div class="form-group row">
                    <div id="docResponse" class="col-12"></div>
                </div>
                <div id="upload_by_file" class="form-group row">
                    <input type="hidden" name="MAX_FILE_SIZE" value="10485760" />
                    <input type="file" class="form-control-file" id="doc_file" name="docs" value="">
                </div>
                <div  id="upload_by_url"  class="form-group row">
                    <label for="document_url" class="col-md-4 col-form-label">Document Url</label>
                    <div class="col-md-8">
                        <input id="document_url" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row">
                    <button class="btn btn-sm btn-danger" id="removeDoc">Remove</button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-success" id="updateDoc" type="button">Update</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade mt-2" id="confirmRemoveDoc" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content border border-danger" style="border-width: 3px !important;">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">Remove doc</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Would you like to remove DOC file?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-orange" data-dismiss="modal">No</button>
                <button type="button" class="btn btn-orange" id="removeDocBtn" data-dismiss="modal">Yes</button>
            </div>
        </div>
    </div>
</div>

<script>

    const btnEditDoc = $('.js-btn-edit-doc');
    const order_id = +btnEditDoc.data('order_id');

    const docRoute = "/orders/update_order_doc";

    btnEditDoc.click(function () {
        $('#document_url').val($(this).data('document_url'));
        $('#editDoc').modal('show');
    });

    $(document).ready(function() {
        $('.change_upload').trigger('click');
    });

    const uploadVars = ['FILE', 'URL'];
    let docMethod = uploadVars[1];

    $('.change_upload').click(function(){
        if (docMethod === uploadVars[1]) {
            docMethod = uploadVars[0];
            $(this).html(uploadVars[1]);
            $('#upload_by_file').show();
            $('#upload_by_url').hide();
        } else {
            docMethod = uploadVars[1];
            $(this).html(uploadVars[0]);
            $('#upload_by_file').hide();
            $('#upload_by_url').show();
        }
    });

    $('#removeDoc').click(function () {
        $('#confirmRemoveDoc').modal('show');
    });

    const docResponse = $('#docResponse');
    const successRequest = function (response) {
        if (response.result !== undefined)
            if (response.result === 'success')
                location.reload();
            else if (response.result === 'error' && response.message !== undefined)
                docResponse.html(`<div class="alert alert-danger">${response.message}</div>`);
    }

    $('#removeDocBtn').click(function () {
        docResponse.html('');
        $.ajax({
            url: docRoute,
            method: "POST",
            data: {
                order_id,
                docMethod: 'REMOVE'
            },
            dataType: "json",
            success: successRequest
        })
    });

    $('#updateDoc').click(function () {
        docResponse.html('');
        let ajaxSet = {
            url: docRoute,
            method: "POST",
            dataType: "json",
            success: successRequest
        }
        if (docMethod === uploadVars[0]) {
            ajaxSet.processData = false;
            ajaxSet.contentType = false;

            let $inputFile = $("#doc_file");
            let fd = new FormData;
            fd.append('order_id', order_id.toString());
            fd.append('docMethod', docMethod);
            fd.append('doc_file', $inputFile.prop('files')[0] || null);

            ajaxSet.data = fd;
        } else {
            ajaxSet.data = {
                docMethod,
                order_id,
                document_url: $('#document_url').val().trim()
            };
        }
        $.ajax(ajaxSet);
        setTimeout(function () {
            docResponse.html('');
        }, 5000);
    });

    $('#docReset').click(function () {
        docResponse.html('');
        $.ajax({
            url: docRoute,
            method: "POST",
            data: {
                order_id,
                docMethod: 'RESET'
            },
            dataType: "json",
            success: successRequest
        })
    });
</script>

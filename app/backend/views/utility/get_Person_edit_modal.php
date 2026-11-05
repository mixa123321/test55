<div class="modal" tabindex="-1" role="dialog" id="editPersonModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="titleModal">Edit person</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div id="edit_person_group" class="col-12 justify-content-center">
                        <div id="person_response"></div>
                        <br>
                        <div class="form-group">
                            <div class="form-group-element">
                                <input id="edit_person_name" type="text" class="form-control form-control-lg" value="">
                                <label for="edit_person_name">Name: <span style="color:red">*</span></label>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-group-element">
                                <input id="edit_person_email" type="text" class="form-control form-control-lg" value="">
                                <label for="edit_person_email">Email: <span style="color:red">*</span></label>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-group-element">
                                <input id="edit_person_phone" type="text" class="form-control form-control-lg" value="">
                                <label for="edit_person_phone">Phone: <span style="color:red">*</span></label>
                            </div>
                        </div>
                    </div>
                    <div class="col-2"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal" id="cancelPerson" type="button">Cancel</button>
                <button class="btn btn-success" id="storePerson" type="button">STORE</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        const storeBtn = $('#storePerson');
        const responseBlock = $('#person_response');
        const Person = new Proxy(
            {
                id: null,
                name: null,
                email: null,
                phone: null,
                owner: false,
            },
            {
                set: function(target, prop, value) {
                    target[prop] = value;
                    $(`input#edit_person_${prop}`).val(value);
                    return true;
                }
            }
        );
        $('#editPerson').on('click', function() {
            responseBlock.html(null);
            const person_id = +$(this).data('person-id');
            if (person_id > 0) {
                Person.id = person_id;
                Person.name = $(this).data('person-name');
                Person.email = $(this).data('person-email');
                Person.phone = $(this).data('person-phone');

                const owner = !!$(this).data('owner');
                if (owner) {
                    $('#edit_person_name').parents('.form-group').remove()
                }
                Person.owner = owner;

                $('#editPersonModal').modal('show');
            }
        });

        $('#edit_person_group').on('change', function(e) {
            const field = e.target.id.replace('edit_person_', '');
            if (Person[field] !== undefined)
                Person[field] = e.target.value;
        })

        storeBtn.on('click',function() {
            responseBlock.html(null);
            $.ajax({
                url: "/users/edit_person",
                method: "POST",
                data: Person,
                dataType: "json",
                success :function (res) {
                    if (res.result !== undefined) {
                        if (res.result === 'error' && res.errors.length > 0) {
                            responseBlock.html(res.errors.reduce((temp, error) => {
                                temp += `<li class="alert-danger p-2">${html_escape(error)}</li>`;
                                return temp;
                            }, ''));
                        } else window.location.reload(false);
                    }
                },
                async: false
            });
        })
    });
</script>
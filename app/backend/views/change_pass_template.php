<?php

use app\core\helpers\View;

?>
<div class="modal fade" id="change_pass_modal" tabindex="-1" role="dialog" aria-labelledby="changePass" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= View::getTranslate('Change password'); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="/account/change_pass" method="POST" autocomplete="off">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col mb-0">
                            <div class="form-group-element">
                                <input type="password" id="old_pass" name="old_pass" class="pass_input form-control form-control-lg" autocomplete="new-password" placeholder=" " value="">
                                <label for="old_pass"><?= View::getTranslate('Old password'); ?></label>
                            </div>
                        </div>
                    </div>
                    <div class="form-row mt-1">
                        <div class="form-group col mb-0">
                            <div class="form-group-element">
                                <input type="password" id="new_pass" name="new_pass" class="pass_input form-control form-control-lg" autocomplete="new-password" placeholder=" " value="">
                                <label for="new_pass"><?= View::getTranslate('New password'); ?></label>
                            </div>
                        </div>
                    </div>
                    <div class="form-row mt-1">
                        <div class="form-group col mb-0">
                            <div class="form-group-element">
                                <input type="password" id="confirm_pass" name="confirm_pass" class="pass_input form-control form-control-lg" autocomplete="new-password" placeholder=" " value="">
                                <label for="confirm_pass"><?= View::getTranslate('Confirm password'); ?></label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <form action="/" method="POST" class="navbar-form navbar-right">
                        <button class="btn btn-outline-orange" type="button" data-dismiss="modal"><?= View::getTranslate('Cancel'); ?></button>
                        <button class="btn btn-orange" type="submit"><?= View::getTranslate('Update'); ?></button>
                    </form>
                </div>
            </form>
        </div>
    </div>
</div>
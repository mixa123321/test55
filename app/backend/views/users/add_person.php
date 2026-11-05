<?php
///**
// * @var $user_role string
// * @var $supplier_id int
// */
//
//use app\core\entities\User;
//use app\core\models\Persons;
//use app\core\helpers\View;
//
//if ($user_role === User::ROLE_GOODSIN_MANAGER)
//    $user_role = User::ROLE_MANAGER;
//
//$back_url = $user_role === User::ROLE_USER ? "user_acc/?id=$supplier_id" : "{$user_role}s";
//
//?>
<!--<div class="container-fluid p-sm-4 p-2">-->
<!--    <!-- Page Heading -->-->
<!--    <div class="d-sm-flex align-items-center justify-content-between mb-4">-->
<!--        <h1 class="h3 mb-0 text-gray-800">Persons</h1>-->
<!--    </div>-->
<!--    <div class="card shadow mb-4">-->
<!--        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">-->
<!--            <h6 class="m-0 font-weight-bold text-primary">-->
<!--                <i class="fas fa-fw fa-user-check"></i> Select person-->
<!--            </h6>-->
<!--        </div>-->
<!--        <form method="post" action="/users/set_staff" id="frm_person">-->
<!--            <div class="card-body">-->
<!--                <input type="hidden" name="role" id="role" value="--><?//= $user_role; ?><!--">-->
<!--                <input type="hidden" name="supplier_id" value="--><?//= $supplier_id; ?><!--">-->
<!--                <div class="row justify-content-center">-->
<!--                    <div class="col-md-10">-->
<!--                        <div class="form-row">-->
<!--                            <div class="col-md-3">-->
<!--                                <div class="form-group">-->
<!--                                    <div class="form-group-element">-->
<!--                                        --><?php
//                                            $persons = Persons::getEmptyUser();
//                                        ?>
<!--                                        <select id="user_id" class="form-control form-control-lg custom-select" name="person[id]" onchange="changeButton();">-->
<!--                                            <option value="0" selected>New person</option>-->
<!--                                            --><?php //foreach ($persons as $_person) { ?>
<!--                                                <option value="--><?//= View::toHtml($_person->getId()) ?><!--">-->
<!--                                                    --><?//= View::toHtml($_person->getName() . " (" . $_person->getEmail() . ")") ?>
<!--                                                </option>-->
<!--                                            --><?php //} ?>
<!--                                        </select>-->
<!--                                        <label for="user_id">Person <span class="text-danger">*</span></label>-->
<!--                                    </div>-->
<!--                                </div>-->
<!--                            </div>-->
<!--                            <div class="col-md-9" id="addPerson">-->
<!--                                <div class="form-row">-->
<!--                                    <div class="col-md-4">-->
<!--                                        <div class="form-group">-->
<!--                                            <div class="form-group-element">-->
<!--                                                <input type="text" name="person[name]" id="name" class="form-control form-control-lg" placeholder=" " value="--><?//= !empty($person) ? $person->getName() : ''; ?><!--">-->
<!--                                                <label for="name">Name <span class="text-danger">*</span></label>-->
<!--                                            </div>-->
<!--                                        </div>-->
<!--                                    </div>-->
<!--                                    <div class="col-md-4">-->
<!--                                        <div class="form-group">-->
<!--                                            <div class="form-group-element">-->
<!--                                                <input type="email" name="person[email]" id="email" class="form-control form-control-lg" placeholder=" " value="--><?//= !empty($person) ? $person->getEmail() : ''; ?><!--">-->
<!--                                                <label for="email">Email <span class="text-danger">*</span></label>-->
<!--                                            </div>-->
<!--                                        </div>-->
<!--                                    </div>-->
<!--                                    <div class="col-md-4">-->
<!--                                        <div class="form-group">-->
<!--                                            <div class="form-group-element">-->
<!--                                                <input type="text" name="person[phone]" id="phone" class="form-control form-control-lg" placeholder=" " value="--><?//= !empty($person) ? $person->getPhone() : ''; ?><!--">-->
<!--                                                <label for="phone">Phone <span class="text-danger">*</span></label>-->
<!--                                            </div>-->
<!--                                        </div>-->
<!--                                    </div>-->
<!--                                </div>-->
<!--                            </div>-->
<!--                        </div>-->
<!--                    </div>-->
<!--                </div>-->
<!--            </div>-->
<!--            <div class="card-footer">-->
<!--                <div class="row">-->
<!--                    <div class="col">-->
<!--                        <a class="btn btn-outline-orange pl-3 btn-sm" href="/users/--><?//= $back_url ?><!--">-->
<!--                            <i class="fas fa-fw fa-chevron-left mr-2"></i>-->
<!--                            <span class="ml-1">Back</span>-->
<!--                        </a>-->
<!--                    </div>-->
<!--                    <div class="col-auto">-->
<!--                        <button class="btn btn-primary btn-icon-split" type="submit">-->
<!--                            <span class="text text-white" id="selPerson">New</span>-->
<!--                            <span class="icon text-white" id="iconPerson">-->
<!--                                <i class="fas fa-fw fa-plus"></i>-->
<!--                            </span>-->
<!--                        </button>-->
<!--                    </div>-->
<!--                </div>-->
<!--            </div>-->
<!--        </form>-->
<!--    </div>-->
<!--</div>-->
<!---->
<!--<script>-->
<!--    function changeButton()-->
<!--    {-->
<!--        var userId = document.getElementById('user_id');-->
<!--        var role = document.getElementById('role');-->
<!--        document.getElementById('selPerson').innerText = (userId.value == 0 ? "New" : "Select");-->
<!--        document.getElementById('iconPerson').innerHTML = "<i class='fas fa-fw fa-" + (userId.value == 0 ? "plus" : "check") + "'></i>";-->
<!--        document.getElementById('frm_person').action = "/users/" + (userId.value == 0 ? "set_staff" : "get_staff");-->
<!--        if (userId.value == 0)-->
<!--            $('#addPerson').show();-->
<!--        else-->
<!--            $('#addPerson').hide();-->
<!--    }-->
<!--</script>-->
<?php

/**
 * @var $all_countries array
 * @var $current_page int
 * @var $id int
 * @var $service_id int
 * @var $name string
 * @var $credentials_key string
 * @var $is_test int
 * @var $countries array
 */

use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\modal\InfoModal;

?>

<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Return carrier</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-fw fa-map-marker-alt"></i> New return carrier
            </h6>
        </div>
        <form method="post" action="/return_carriers/save">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="c" value="<?= $current_page ?>">
            <textarea name="countries" id="countries" style="display:none;"><?= View::toHtml(json_encode($countries)); ?></textarea>
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <div class="form-row">
                            <div class="col-sm-4 col-lg-2">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input type="number" name="service_id" id="service_id" class="form-control form-control-lg" value="<?= $service_id ?>" min="1" step="any">
                                        <label for="service_id">Service <span style="color:red">*</span></label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-8 col-lg-3">
                                <div class="form-group">
                                    <div class="form-group">
                                        <div class="form-group-element">
                                            <input type="text" name="name" id="name" class="form-control form-control-lg" value="<?= View::toHtml($name) ?>">
                                            <label for="name">Name <span style="color:red">*</span></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-8 col-lg-3">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input type="text" name="credentials_key" id="credentials_key" class="form-control form-control-lg" value="<?= View::toHtml($credentials_key) ?>">
                                        <label for="credentials_key">Credentials key <span style="color:red">*</span></label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4 col-lg-3">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label for="is_test">Test</label>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="is_test" name="is_test" <?= $is_test ? ' checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4 col-lg-3">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <?php
                                        $options = [
                                            'class' => 'form-control form-control-lg custom-select',
                                            'id' => 'select_country',
                                        ];
                                        echo Html::dropDownList('destination_country', $all_countries, empty($countries[0]) ? null : $countries[0], $options);
                                        ?>
                                        <label for="select_country">Destination country</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-6">
                                <div class="form-group">
                                    <div class="form-group-element" id="active-countries"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-outline-orange pl-3 btn-sm" href="/return_carriers?c=<?= $current_page; ?>">
                            <i class="fas fa-fw fa-chevron-left mr-2"></i>
                            <span class="ml-1">Back</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-icon-split" type="submit">
                            <span class="text text-white">Done</span>
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-chevron-right"></i>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$modal_destination_country = new InfoModal('destinationCountry', 'Warning', InfoModal::SIZE_NORMAL);
echo $modal_destination_country->addContent(Html::error('Destination country must be selected'))->put();
?>

<script>
    $(function () {
        let all_countries = <?= View::toJs($all_countries); ?>;
        let countries = <?= View::toJs($countries); ?>;

        function addCountry(country_code, country) {
            let html =
                '<button class="btn btn-success btn-icon-split m-1 active-country" type="button" value="' + html_escape(country_code) + '">' +
                '<span class="text text-white">' + html_escape(country) + ' (' + html_escape(country_code) + ')' + '</span>' +
                '<span class="icon text-white"><i class="fas fa-fw fa-times"></i></span>' +
                '</button>';
            $('#active-countries').append(html);
            $('#countries').text(JSON.stringify(countries));
        }

        for (let country_code of countries) {
            let country = all_countries[country_code];
            addCountry(country_code, country);
        }

        $('#select_country').change(function () {
            let country_code = this.value;
            let country = all_countries[country_code];
            if (countries.indexOf(country_code) !== -1) {
                return;
            }
            countries.push(country_code);
            addCountry(country_code, country);
        });

        $(document).on("click", ".active-country", function() {
            if (countries.length === 1) {
                <?= $modal_destination_country->show(); ?>
                return;
            }
            let country_code = this.value;
            let index = countries.indexOf(country_code);
            if (index !== -1) {
                countries.splice(index, 1);
            }
            $(this).remove();
            $('#countries').text(JSON.stringify(countries));
            $("#select_country").val(countries[0]);
        });
    })
</script>
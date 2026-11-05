<?php

use app\core\helpers\modal\Modal;
use app\core\helpers\View;

/**
 * @var array $parcelCategories
 * @var bool $requiresParcelCategory
 * @var Modal $modal
 * @var bool $allowEmptySelection Whether to show an empty option in selects
 */
$modal->addCustomButtons('<button class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Confirm</button>');
?>

<?php if (!empty($parcelCategories)): ?>
<div class="alert alert-info mb-3">
    You can select individual parcel categories or use the order-level category if no selection is made.
    The default order-level category is "Commercial Goods".
</div>
<button type="button" class="btn btn-secondary btn-sm mb-3 btn-copy-first-category" style="width: 100%;">Copy First Category to All Packages</button>
<div class="packages-container"></div>
<?php else: ?>
<div class="alert alert-info">
    No categories are available for this shipping method. The order-level category will be used.
</div>
<?php endif; ?>

<script>
    $(function() {
        const $modal = $('<?= $modal->getSelector() ?>'),
              $packagesContainer = $modal.find('.packages-container'),
              $validationError = $modal.find('.validation-error'),
              $confirmBtn = $modal.find('[type="submit"]'),
              $copyBtn = $modal.find('.btn-copy-first-category');
        
        // Function to create category selectors for each package
        function createPackageSelectors(boxes) {
            $packagesContainer.empty();
            
            if (boxes.length === 0) {
                // If no boxes, show a message
                $('<div class="alert alert-info">').text('No packages found. Please create at least one package first.').appendTo($packagesContainer);
                $copyBtn.hide();
                return;
            }
            
            // Create a selector for each box
            boxes.forEach(function(box, index) {
                const boxId = box.id;
                const boxName = box.name || `Box ${boxId}`;
                
                const $categoryGroup = $('<div class="form-group">').appendTo($packagesContainer);
                if (index === boxes.length - 1) {
                    $categoryGroup.addClass('mb-0');
                }
                const $formGroupElement = $('<div class="form-group-element">').appendTo($categoryGroup);
                
                const $select = $('<select>')
                    .addClass('form-control form-control-lg custom-select')
                    .attr('name', 'package_category_' + boxId)
                    .attr('data-package-id', boxId)
                    .attr('data-required', 'false')
                    .appendTo($formGroupElement);
                
                $('<label>').text(boxName + ' Category').appendTo($formGroupElement);
                
                <?php if ($allowEmptySelection ?? true): ?>
                $('<option value="">Use Order Category</option>').appendTo($select);
                <?php endif; ?>
                
                <?php foreach ($parcelCategories as $key => $category) : ?>
                $('<option>').val('<?= View::toHtml($key); ?>').text('<?= View::toHtml($category); ?>').appendTo($select);
                <?php endforeach; ?>
            });
            
            // Show copy button only if there are multiple boxes
            $copyBtn.toggle(boxes.length > 1);
        }
        
        // When the modal is about to show, prepare selectors
        $modal.on('show.bs.modal', function() {
            let boxes = [];
            
            // Get all boxes from the page
            $('.order-box').each(function() {
                const boxId = $(this).data('box-id');
                const boxName = $(this).find('.title').text().trim();
                boxes.push({
                    id: boxId,
                    name: boxName
                });
            });
            
            // Create selectors for each box
            createPackageSelectors(boxes);
        });
        
        // When the modal is fully shown, focus on the first select
        $modal.on('shown.bs.modal', function() {
            $packagesContainer.find('select:first').focus();
        });
        
        // Copy first package category to all other packages
        $copyBtn.on('click', function() {
            const $selects = $packagesContainer.find('select');
            if ($selects.length > 1) {
                const firstValue = $selects.first().val();
                $selects.slice(1).val(firstValue).trigger('change');
            }
        });
        
        // Handle confirmation
        $confirmBtn.on('click', function() {
            const packageCategories = {};
            
            // Collect values from all selects
            $packagesContainer.find('select').each(function() {
                const $select = $(this),
                      packageId = $select.data('package-id'),
                      value = $select.val();
                
                if (value) {
                    packageCategories[packageId] = value;
                }
            });
            
            $modal.trigger('parcelCategorySelected', [packageCategories]);
            $modal.modal('hide');
        });
    });
</script>

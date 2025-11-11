<?php
/**
 * View for side bar
 *
 * @since 3.7.0
 *
 * @var                       $post_id
 * @var                       $automatic
 * @var                       $in_future_stock
 */

defined( 'ABSPATH' ) || die;

do_action( 'omom_pre_orders_shipment_action_metabox', $post_id );

// hide delete action yet
if (false) :?>
    <div id="delete-action">
        <?php if (current_user_can( 'delete_post', $post_id )) { ?>
            <a 
                class="submitdelete deletion" 
                href="<?= esc_url( get_delete_post_link( $post_id ) ) ?>"
            >
                Delete permanently
            </a>
        <?php }?>
    </div>
<?php endif; ?>

<br>

<div id="automatic-stock-add-action">
    <label class="switch">
        <input 
            type="checkbox" 
            id="rs-automatic" 
            name="rs_automatic" 
            <?= "on" === $automatic ? "checked" : "" ?>
        />
        <span class="slider"></span> 
    </label>	
    <label for="rs_automatic_add">
        Add stock automatically on arrival
    </label>
</div>

<br>

<div id="show-in-future-stock-action">
    <label class="switch">
        <input 
            type="checkbox" 
            id="rs-in-future-stock" 
            name="rs_show_in_future_stock" 
            <?= "on" === $in_future_stock ? "checked" : "" ?>
        />
        <span class="slider"></span>
    </label>	
    <label for="rs_show_in_future_stock">
        Show on Future Stock pages
    </label>		
</div>

<div id="publishing-action">
    <span class="spinner"></span>
    <!-- Does not include create yet -->
    <input
        id="omom_release_button"
        class="button button-primary button-large" 
        type="button" 
        value="Release"
    />	
    <dialog
        id="omom_release_modal"
    >
        <h2 class="omom-text-center">YOU ARE ABOUT TO RELEASE THIS SHIPMENT</h2>
        <p class="omom-text-center">This will have the following consequences:</p>
        <ul class="omom-list-disc omom-max-w-md omom-text-left omom-mx-auto omom-px-4">
            <li>The pre-order quantity of each product will<br> be added to the actual stock of that product.</li>
            <li>The available quantity of each product will be set to 0.</li>
            <li>The shipment status will be set to "Released".</li>
        </ul>
        <div class="omom-flex omom-flex-row omom-gap-2">
            <input
                type="submit" 
                class="button button-primary button-large" 
                name="omom_release_shipment"
                type="button" 
                value="Release" 
            />
            <input 
                id="omom_abort_release"
                class="button button-primary button-large" 
                type="button"
                value="Abort"
            />
        </div>
    </dialog>			
</div>
<div class="clear"></div>
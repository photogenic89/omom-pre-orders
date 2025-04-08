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
        class="button button-primary button-large" 
        type="submit" 
        name="rs_release_po_stock" 
        value="Release"
    />				
</div>
<div class="clear"></div>
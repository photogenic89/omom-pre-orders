<?php
/**
 * View for side bar
 *
 * @since 1.0.4
 *
 * @var                       $automatic
 * @var string                $time
 * @var string                $container
 * @var string                $comment
 */

defined( 'ABSPATH' ) || die;
?>
<br>

<label for="rs_arrival">
    <strong>Time of arrival</strong>
</label>
<br>
<br>
<input 
    type="datetime-local" 
    id="rs-arrival" 
    name="rs_arrival" 
    value="<?= $time ?>" 
    style="width:100%" 
    required
/>

<br>
<br>
<br>

<label for="rs_container">
    <strong>Container name</strong>
</label>
<br>
<br>
<input 
    type="text" 
    id="rs-container" 
    name="rs_container" 
    value="<?= $container ?>" 
    style="width:100%"
/>

<br>
<br>
<br>

<label>
    <strong>Custom notes</strong>
</label>
<br>
<br>
<textarea 
    id="rs-comment" 
    name="rs_comment" 
    style="width:100%"
>
    <?= $comment ?>
</textarea>
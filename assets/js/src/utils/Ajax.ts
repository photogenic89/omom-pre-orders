
interface Ajax {
    url: string;
    nonce: string;
}   

declare const settingsInfoObject: Ajax;

jQuery( function($) {
const schedule = {

    defaultAjax: {
        url: settingsInfoObject.url,
        type: 'POST',
        data: {
            action: 'omom_preorder_admin_ajax',
            ajax_nonce: settingsInfoObject.nonce
        },
        beforeSend : ( xhr ) => {
            //button.text('Loading...');
        },
        success: ( response ) => {
            console.log(response)
        },
        error: ( err ) => {
            console.log( err );
        }
    },

    init: function() {
        this.solutionClick()
    },

    solutionClick: function() {
        const solves = document.getElementsByClassName('error-solve')

        for ( let i = 0; i < solves.length; i++ ) {
            solves[i].addEventListener( 'click', e => { 
                e.preventDefault()
                this.solutionAjax(e.target)
            })
        }

        document.querySelector('input[id=omom_pre_order_only_dates]').addEventListener( 'change', e => {
            e.preventDefault();
            this.clickCheckboxAjax( e.target, "checked" in e.target ? e.target.checked : false )
        })
    },

    solutionAjax: function( target ) {

        const parent = target.closest('.error-field');

        let newAjax = this.defaultAjax;      

        newAjax.data.id = parent.querySelector('.hidden-id-ref').innerText;
        newAjax.data.option = parent.querySelector('.hidden-issue-ref').innerText;

        newAjax.success = ( response ) => {

            var success = document.createElement('div')

            success.classList.add('task-solved')
            success.innerText = 'Issue resolved!'
            parent.insertAdjacentElement('afterend', success)
    
            if (parent !== null) parent.remove()

            console.log(response)
        }

        $.ajax( newAjax );
    },

    clickCheckboxAjax: function( target, checked: boolean ) {

        console.log( "clicked checkbox", checked, target );

        let newAjax = this.defaultAjax;  

        newAjax.data.id = 'omom_pre_order_only_dates';
        newAjax.data.isChecked = checked;
        newAjax.data.option = "checkbox_changed";

        $.ajax( newAjax )
    }
}

schedule.init()

})
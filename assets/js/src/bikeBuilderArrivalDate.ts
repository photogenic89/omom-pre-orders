;( function ( $, window, document ) {

    // composite products api
	$( '.composite_data' ).on( 'wc-composite-initializing', function( event, composite ) {   

        /**
         * Show sold out components differently
         */
        composite.actions.add_action( 'composite_initialized', function (){

            const steps = composite.steps

            for(const step of steps) {
                if(!step.is_component()) continue
                //console.log(step.is_in_stock())
                for(const option of step.$component_options) {
                }
            }
    
        })
        
        /**
         * Bike Builder V2 - Restock arrival dates
         */
        composite.actions.add_action( 'component_selection_changed', function( component: any ){

            // set timeout because otherwise it won't receive the right date at times
            setTimeout(function(){

                var compID    = component.step_id.toString(),
                    available = composite.api.get_component_availability(compID),
                    restock   = available.replace(/(<([^>]+)>)/gi, ""),
                    wrap      = $('.cart.composite_data > .composite_wrap'),
                    isNum     = /^\d+$/.test(restock)      

                // move restock note down depending on selection 
                if( restock.length > 0 && isNum ) {

                    // show restock num as date in component
                    $(".component_content[data-product_id='" + compID + "'] .restock-available").css('color','#0073aa').text("Pre-order now. In stock: " + unixDateToString(restock))

                    // generate markup
                    if( wrap.find('.arrival-date-container').length === 0 ) {
                        wrap.prepend('<div class="arrival-date-container"><div class="arrival-dates"></div><div class="arrival-date-summary"></div></div>')
                        $('.arrival-dates').css('display','none')
                    }
                    
                    var productID = component.get_selected_product(),
                        varID     = component.get_selected_variation(),
                        varIdAttr = varID === '' ? '' : 'variationID="' + varID + '"',
                        dates     = $('.arrival-dates'),
                        compDate  = $('.arrival-of-' + compID),
                        hasDate   = compDate.length,
                        secTitle  = composite.api.get_component_configuration(compID)

                    // add - if doesn't exist yet
                    if( hasDate === 0 ) {
                        dates.prepend('<span class="arrival-of-' + compID + '" productID="' + productID + '" ' + varIdAttr + '>' + restock + '</span>')
                    } 
                    else {
                        // check if needs update or needs to be deleted
                        if ( restock !== compDate.text() ) {
                            // update - if dates have changed - doesn't take in account which's product date that is
                            compDate.text(restock).attr({ "productID":productID, "variationID":varID}) 
                        } 
                        else if ( secTitle.selection_title === "No selection" || ( compDate.attr('variationID') && !varID && secTitle.selection_meta.length === 0 ) ) {
                            // remove - if selection is cleared 
                            compDate.remove()
                        }
                    }
                    
                    arrivalSequence()

                } 
                else if ( wrap.find('.arrival-dates .arrival-of-' + compID).length > 0 ) {
                    wrap.find('.arrival-dates .arrival-of-' + compID).remove()
                    arrivalSequence()
                }

                function arrivalSequence() {
                    
                    var listDates: any[] = [],
                        maxDate   = 0,
                        date      = '',
                        summary   = $('.arrival-date-summary')

                    $('.arrival-dates').children().each( function(){
                        listDates.push(parseInt( $(this).text() )) 
                    })

                    if( listDates.length > 0 ) {
                        maxDate = Math.max(...listDates)
                        date    = 'Expected in stock: ' + unixDateToString(maxDate)

                        if( summary.find('span').length === 0 ) {
                            summary.append('<span style="color:#0073aa">' + date + '</span>')
                        } else {
                            summary.find('span').text(date)
                        }
                    } 
                    else {
                        // if no restocks are left, remove the paragraph
                        summary.find('span').remove()
                    }
                }

                function unixDateToString(timestamp: number ){
                    
                    let date = new Date(timestamp * 1000);
                    let dayDate = date.toLocaleString('en-GB', { day: 'numeric'});
                    let dateString = date.toLocaleString('en-GB', { month: 'long', year: 'numeric'})

                    dayDate = dayDate + dateOrdinal(+dayDate)

                    return dayDate + ' of ' + dateString
                }

                function dateOrdinal(d: number) {
                    if (d > 3 && d < 21) return 'th';
                    switch (d % 10) {
                        case 1:  return "st";
                        case 2:  return "nd";
                        case 3:  return "rd";
                        default: return "th";
                    }
                }

                // Change input max based on selection
                function change_max_input() {

                    // Variations are not yet correctly supported

                    const config = composite.api.get_composite_configuration(),
                          cart   = $( '.composite_wrap .quantity .qty' )
                    
                    let max = 0,
                        new_max: number,
                        value: string,
                        first = true

                    for( const comp in config ) {
                        value = $('#component_' + comp + ' .quantity_button .qty').attr('max')

                        if( value === "" ) continue;
                    
                        new_max = parseInt(value)

                        if( isNaN(new_max) ) continue;

                        max     = ( first || max > new_max ) ? new_max : max
                        first   = false
                    }

                    if( first ) {
                        cart.attr( 'max', '' )
                        return
                    }

                    cart.attr( 'max', max )
                }
                // change_max_input()

            },500)

        }, 100, composite.api.get_steps() );
	});
    
// @ts-ignore
} ) ( jQuery, window, document );
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$(document).ready(function() {
    var match = $(location).attr("href").match(/\/([a-z0-9\-]+\.html)(?:[#?]|$)/);
    var pageURL = match ? match[1] : 'index.html';
    console.log(pageURL);
    var li = $('nav a[href="' + pageURL + '"]').closest('li').attr("id","selected");
    li.addClass('selected');
    $('nav .menu2 a[href*="' + pageURL + '"]').closest('ul').addClass('selected');
    $('nav .menu3 a[href*="' + pageURL + '"]').closest('ul').addClass('selected');
    $('nav').scrollTo('.menu1 > li.selected');

    $('#navtoggle').on('click', function() {
        var $nav = $('nav');
        var isOpen = $nav.toggleClass('open').hasClass('open');
        $(this).attr('aria-expanded', isOpen ? 'true' : 'false');
    });

    // On mobile, an anchor link within the current page doesn't reload, so
    // close the overlay so the user can actually see the section.
    $('nav a').on('click', function() {
        $('nav').removeClass('open');
        $('#navtoggle').attr('aria-expanded', 'false');
    });

    // $('#search form').on('submit', function (e){
    //     $('#search input[name=q]').val($('#searchinput').val() + " site:help.adam.co.za");
    //     $('article').innerHTML = '';
    //     alert ($('#search input[name=q]').val());
    // });
});
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$(document).ready(function() {
    var pageURL = $(location).attr("href").match(/\/([a-z\-]+\.html)#?/) [1];
    console.log(pageURL);
    var li = $('nav a[href="' + pageURL + '"]').closest('li').attr("id","selected");
    li.addClass('selected');
    $('nav .menu2 a[href*="' + pageURL + '"]').closest('ul').addClass('selected');
    $('nav .menu3 a[href*="' + pageURL + '"]').closest('ul').addClass('selected');
    $('nav').scrollTo('.menu1 > li.selected');

    // $('#search form').on('submit', function (e){
    //     $('#search input[name=q]').val($('#searchinput').val() + " site:help.adam.co.za");
    //     $('article').innerHTML = '';
    //     alert ($('#search input[name=q]').val());
    // });
});
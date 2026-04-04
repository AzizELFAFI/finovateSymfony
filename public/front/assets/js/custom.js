jQuery( document ).ready(function( $ ) {


	"use strict";


    
        $(function() {
            $( "#tabs" ).tabs();
        });


        // Page loading animation

        $("#preloader").animate({
            'opacity': '0'
        }, 600, function(){
            setTimeout(function(){
                $("#preloader").css("visibility", "hidden").fadeOut();
            }, 300);
        });       

        $(window).scroll(function() {
          var scroll = $(window).scrollTop();
          var box = $('.header-text').height();
          var header = $('header').height();

          if (scroll >= box - header) {
            $("header").addClass("background-header");
          } else {
            $("header").removeClass("background-header");
          }
        });
		if ($('.owl-testimonials').length) {
            $('.owl-testimonials').owlCarousel({
                loop: true,
                nav: false,
                dots: true,
                items: 1,
                margin: 30,
                autoplay: false,
                smartSpeed: 700,
                autoplayTimeout: 6000,
                responsive: {
                    0: {
                        items: 1,
                        margin: 0
                    },
                    460: {
                        items: 1,
                        margin: 0
                    },
                    576: {
                        items: 2,
                        margin: 20
                    },
                    992: {
                        items: 2,
                        margin: 30
                    }
                }
            });
        }
        if ($('.owl-partners').length) {
            $('.owl-partners').owlCarousel({
                loop: true,
                nav: false,
                dots: true,
                items: 1,
                margin: 30,
                autoplay: false,
                smartSpeed: 700,
                autoplayTimeout: 6000,
                responsive: {
                    0: {
                        items: 1,
                        margin: 0
                    },
                    460: {
                        items: 1,
                        margin: 0
                    },
                    576: {
                        items: 2,
                        margin: 20
                    },
                    992: {
                        items: 4,
                        margin: 30
                    }
                }
            });
        }

        $(".Modern-Slider").slick({
            autoplay:true,
            autoplaySpeed:10000,
            speed:600,
            slidesToShow:1,
            slidesToScroll:1,
            pauseOnHover:false,
            dots:true,
            pauseOnDotsHover:true,
            cssEase:'linear',
           // fade:true,
            draggable:false,
            prevArrow:'<button class="PrevArrow"></button>',
            nextArrow:'<button class="NextArrow"></button>', 
        });

        function visible(partial) {
            var $t = partial,
                $w = jQuery(window),
                viewTop = $w.scrollTop(),
                viewBottom = viewTop + $w.height(),
                _top = $t.offset().top,
                _bottom = _top + $t.height(),
                compareTop = partial === true ? _bottom : _top,
                compareBottom = partial === true ? _top : _bottom;

            return ((compareBottom <= viewBottom) && (compareTop >= viewTop) && $t.is(':visible'));

        }

        $(window).scroll(function(){

          if(visible($('.count-digit')))
            {
              if($('.count-digit').hasClass('counter-loaded')) return;
              $('.count-digit').addClass('counter-loaded');
              
        $('.count-digit').each(function () {
          var $this = $(this);
          jQuery({ Counter: 0 }).animate({ Counter: $this.text() }, {
            duration: 3000,
            easing: 'swing',
            step: function () {
              $this.text(Math.ceil(this.Counter));
            }
          });
        });
        }
    })

        function validateContactForm(form) {
            var nameEl = form.querySelector('input[name="name"]');
            var emailEl = form.querySelector('input[name="email"]');
            var subjectEl = form.querySelector('input[name="subject"]');
            var messageEl = form.querySelector('textarea[name="message"]');

            var errors = [];

            var name = nameEl ? (nameEl.value || '').trim() : '';
            var email = emailEl ? (emailEl.value || '').trim() : '';
            var subject = subjectEl ? (subjectEl.value || '').trim() : '';
            var message = messageEl ? (messageEl.value || '').trim() : '';

            if (!name) {
                errors.push('Le nom complet est obligatoire.');
            } else if (/^\d+$/.test(name)) {
                errors.push('Le nom complet ne doit pas être un entier.');
            }

            if (!email) {
                errors.push('L\'adresse e-mail est obligatoire.');
            } else if (!/^\S+@\S+\.\S+$/.test(email)) {
                errors.push('Veuillez saisir une adresse e-mail valide.');
            }

            if (subjectEl) {
                if (!subject) {
                    errors.push('Le sujet est obligatoire.');
                } else if (/^\d+$/.test(subject)) {
                    errors.push('Le sujet ne doit pas être un entier.');
                }
            }

            if (!message) {
                errors.push('Le message est obligatoire.');
            }

            var existingAlert = form.querySelector('.contact-validation-alert');
            if (existingAlert) {
                existingAlert.remove();
            }

            if (errors.length > 0) {
                var alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger contact-validation-alert';
                alertDiv.setAttribute('role', 'alert');
                alertDiv.innerHTML = errors.join('<br>');

                form.prepend(alertDiv);
                return false;
            }

            return true;
        }

        document.querySelectorAll('form[action*="/contact/send"]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (!validateContactForm(form)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        });
 
});

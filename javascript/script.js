        document.addEventListener('DOMContentLoaded', function () {
            function initOffersSwiper() {
                if (typeof Swiper === 'undefined') {
                    return setTimeout(initOffersSwiper, 50);
                }
                new Swiper('.offers-swiper', {
                    loop: false,
                    speed: 500,
                    grabCursor: true,
                    autoplay: { delay: 4000, disableOnInteraction: false },
                    spaceBetween: 20,
                    slidesPerView: 1.15,
                    navigation: {
                        nextEl: '.offers-swiper .swiper-button-next',
                        prevEl: '.offers-swiper .swiper-button-prev',
                    },
                    pagination: {
                        el: '.offers-swiper .swiper-pagination',
                        clickable: true,
                    },
                    breakpoints: {
                        560:  { slidesPerView: 2.1 },
                        860:  { slidesPerView: 3.1 },
                        1180: { slidesPerView: 4 },
                    },
                });
            }
            initOffersSwiper();
        });
        
        
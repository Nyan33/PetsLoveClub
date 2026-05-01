<?php $__base = function_exists('nav_base') ? nav_base() : ''; ?>
<footer id="contacts" class="bg-gray-900 text-white py-16 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
            <div>
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-12 h-12 bg-rose-400 rounded-2xl flex items-center justify-center">
                        <i data-lucide="heart" class="w-6 h-6 text-white fill-white"></i>
                    </div>
                    <h3 class="text-xl font-black">PetLove Club</h3>
                </div>
                <p class="text-gray-400 font-medium mb-4">Профессиональный клуб любителей животных с 1999 года</p>
                <div class="flex space-x-3">
                    <a href="#" class="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center hover:bg-rose-400 transition-colors">
                        <svg role="img" viewBox="0 0 24 24" class="w-5 h-5 fill-white" xmlns="http://www.w3.org/2000/svg"><title>VK</title><path d="m9.489.004.729-.003h3.564l.73.003.914.01.433.007.418.011.403.014.388.016.374.021.36.025.345.03.333.033c1.74.196 2.933.616 3.833 1.516.9.9 1.32 2.092 1.516 3.833l.034.333.029.346.025.36.02.373.025.588.012.41.013.644.009.915.004.98-.001 3.313-.003.73-.01.914-.007.433-.011.418-.014.403-.016.388-.021.374-.025.36-.03.345-.033.333c-.196 1.74-.616 2.933-1.516 3.833-.9.9-2.092 1.32-3.833 1.516l-.333.034-.346.029-.36.025-.373.02-.588.025-.41.012-.644.013-.915.009-.98.004-3.313-.001-.73-.003-.914-.01-.433-.007-.418-.011-.403-.014-.388-.016-.374-.021-.36-.025-.345-.03-.333-.033c-1.74-.196-2.933-.616-3.833-1.516-.9-.9-1.32-2.092-1.516-3.833l-.034-.333-.029-.346-.025-.36-.02-.373-.025-.588-.012-.41-.013-.644-.009-.915-.004-.98.001-3.313.003-.73.01-.914.007-.433.011-.418.014-.403.016-.388.021-.374.025-.36.03-.345.033-.333c.196-1.74.616-2.933 1.516-3.833.9-.9 2.092-1.32 3.833-1.516l.333-.034.346-.029.36-.025.373-.02.588-.025.41-.012.644-.013.915-.009ZM6.79 7.3H4.05c.13 6.24 3.25 9.99 8.72 9.99h.31v-3.57c2.01.2 3.53 1.67 4.14 3.57h2.84c-.78-2.84-2.83-4.41-4.11-5.01 1.28-.74 3.08-2.54 3.51-4.98h-2.58c-.56 1.98-2.22 3.78-3.8 3.95V7.3H10.5v6.92c-1.6-.4-3.62-2.34-3.71-6.92Z"/></svg>
                    </a>
                    <a href="#" class="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center hover:bg-rose-400 transition-colors">
                        <svg role="img" viewBox="0 0 24 24" class="w-5 h-5 fill-white" xmlns="http://www.w3.org/2000/svg"><title>Telegram</title><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                    </a>
                </div>
            </div>

            <div>
                <h4 class="font-bold mb-4 text-lg">Быстрые ссылки</h4>
                <ul class="space-y-2">
                    <li><a href="<?= $__base ?>about.php"       class="text-gray-400 hover:text-rose-400 transition-colors font-medium">О клубе</a></li>
                    <li><a href="<?= $__base ?>champions.php"   class="text-gray-400 hover:text-rose-400 transition-colors font-medium">Чемпионы</a></li>
                    <li><a href="<?= $__base ?>news.php"        class="text-gray-400 hover:text-rose-400 transition-colors font-medium">Новости</a></li>
                    <li><a href="<?= $__base ?>events.php"      class="text-gray-400 hover:text-rose-400 transition-colors font-medium">События</a></li>
                </ul>
            </div>

            <div>
                <h4 class="font-bold mb-4 text-lg">Услуги</h4>
                <ul class="space-y-2">
                    <li><a href="<?= $__base ?>events.php"                              class="text-gray-400 hover:text-rose-400 transition-colors font-medium">Выставки</a></li>
                    <li><a href="<?= $__base ?>knowledge_base.php?slug=breeding"        class="text-gray-400 hover:text-rose-400 transition-colors font-medium">Племенная работа</a></li>
                    <li><a href="<?= $__base ?>knowledge_base.php?slug=training"        class="text-gray-400 hover:text-rose-400 transition-colors font-medium">Обучение</a></li>
                    <li><a href="<?= $__base ?>knowledge_base.php"                      class="text-gray-400 hover:text-rose-400 transition-colors font-medium">База знаний</a></li>
                </ul>
            </div>

        </div>

        <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center">
            <p class="text-gray-400 text-sm font-medium">© 2026 PetLove Club. Все права защищены.</p>
            <div class="flex space-x-6 mt-4 md:mt-0">
                <a href="#" class="text-gray-400 hover:text-rose-400 text-sm font-medium transition-colors">Политика конфиденциальности</a>
                <a href="#" class="text-gray-400 hover:text-rose-400 text-sm font-medium transition-colors">Пользовательское соглашение</a>
            </div>
        </div>
    </div>
</footer>

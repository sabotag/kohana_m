<div class="span3">
    <?=Request::factory(Route::url('default', array('controller' => 'comment', 'action' => 'last')))->execute()?>
</div>
<div class="span9 popular">
    <?$action = Request::current()->action();?>
    <div class="block_title">
        <?if ($action == 'user'):?>
            <?=$owner['firstname']?>
        <?elseif ($action == 'tag'):?>

        <?else:?>
            Популярное
        <?endif;?>
    </div>
    <div class="well well-small  pull-right">
        фф… А что, если я скажу вам, что автоматизировал все эти действия по-максимуму? Да, теперь вам нужно выполнить всего пару команд и автодеплой запущен и работает! И все это благодаря Гитхабайзеру.
    </div>
    <div class="thum_items">
        <?if (sizeof($materials)):?>
        <ul class="thumbnails">
            <?foreach ($materials as $m):?>
                <?
                $comm = Arr::get($comments[$m->id()], 'count', 0);
                $like = Arr::get($polls[$m->id()], 'like', 0);
                $dislike = Arr::get($polls[$m->id()], 'dislike', 0)
                ?>
            <li class="thum_item">
                <a href="<?=Route::url('default', array('controller' => 'material', 'action' => 'show', 'id' => $m->id()))?>" class="thumbnail">
                    <img src="/<?=$m->thumb()?>" alt="<?=$m->title?>"/>
                </a>
                <div class="item_txt">
                    <?=Text::limit_words($m->description, 15)?>
                </div>
                <div class="item_stat">Комм <?=$comm?>. &nbsp; <?=$like?><i class="icon-thumbs-up"></i>&nbsp;&nbsp; <?=$dislike?><i class="icon-thumbs-down"></i></div>
            </li>
            <?endforeach;?>
        </ul>
        <?endif;?>
    </div>
</div>


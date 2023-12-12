<?php

namespace Bakerkretzmar\NovaSettingsTool\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingsToolController
{
    protected $store;

    public function getKeyParts($key)
    {
        $keyParts = explode('.', $key);
        $keyGroup = array_shift($keyParts);
        $keyName = implode('.', $keyParts);

        return [$keyGroup, $keyName];
    }

    public function getSettings($group)
    {
        $class = '\App\Settings\\'.Str::studly($group).'Settings';

        if (! class_exists($class)) {
            return null;
        }

        return app($class);
    }

    public function read()
    {
        $settings = collect(config('nova-settings-tool.settings'));

        $panels = $settings->where('panel', '!=', null)->pluck('panel')->unique()
            ->flatMap(function ($panel) use ($settings) {
                return [$panel => $settings->where('panel', $panel)->pluck('key')->all()];
            })
            ->when($settings->where('panel', null)->isNotEmpty(), function ($collection) use ($settings) {
                return $collection->merge(['_default' => $settings->where('panel', null)->pluck('key')->all()]);
            })
            ->all();

        $settings = $settings->map(function ($setting) {
            [$keyGroup, $keyName] = $this->getKeyParts($setting['key']);

            $settings = $this->getSettings($keyGroup);

            if (! property_exists($settings, $keyName)) {
                return null;
            }

            return array_merge([
                'type' => 'text',
                'label' => ucfirst($setting['key']),
                'value' => $settings->{$keyName} ?? null,
            ], $setting);
        })
            ->keyBy('key')
            ->all();

        return response()->json([
            'title' => config('nova-settings-tool.title', 'Settings'),
            'settings' => $settings,
            'panels' => $panels,
        ]);
    }

    public function write(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            [$keyGroup, $keyName] = $this->getKeyParts($key);

            $settings = $this->getSettings($keyGroup);

            if (! property_exists($settings, $keyName)) {
                return null;
            }

            $settings->{$keyName} = $value;

            $settings->save();
        }

        return response()->json();
    }
}

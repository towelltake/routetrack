<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChannelController extends Controller
{
    public function index()
    {
        return Inertia::render('operation/Channel', [
            'channels' => ChannelMaster::orderBy('channelcode')->get([
                'channelcode', 'alternatecode', 'channelname', 'arbchannelname', 'activestatus',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'alternatecode'  => 'nullable|string|max:50',
            'channelname'    => 'required|string|max:50',
            'arbchannelname' => 'nullable|string|max:50',
            'activestatus'   => 'required|integer',
        ]);

        $data['created']  = auth()->user()->name;
        $data['cdat']     = now();
        $data['modified'] = auth()->user()->name;
        $data['mdat']     = now();

        ChannelMaster::create($data);
        return back();
    }

    public function update(Request $request, ChannelMaster $channel)
    {
        $data = $request->validate([
            'alternatecode'  => 'nullable|string|max:50',
            'channelname'    => 'required|string|max:50',
            'arbchannelname' => 'nullable|string|max:50',
            'activestatus'   => 'required|integer',
        ]);

        $data['modified'] = auth()->user()->name;
        $data['mdat']     = now();

        $channel->update($data);
        return back();
    }

    public function destroy(ChannelMaster $channel)
    {
        try {
            $channel->delete();
        } catch (\Exception $e) {
            return back()->withErrors(['delete' => 'Cannot delete: record is in use.']);
        }
        return back();
    }
}

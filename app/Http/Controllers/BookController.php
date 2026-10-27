<?php

namespace App\Http\Controllers;

use App\Enums\PartyKind;
use App\Http\Requests\BookRequest;
use App\Models\Book;
use App\Models\PaperType;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('books.index', [
            'books' => Book::query()
                ->with(['formats.paperType', 'party'])
                ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('name_ur', 'like', "%{$search}%")->orWhere('class_subject', 'like', "%{$search}%")))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return $this->form(new Book(['is_active' => true]));
    }

    public function store(BookRequest $request): RedirectResponse
    {
        $book = DB::transaction(function () use ($request): Book {
            $book = Book::create($this->bookAttributes($request));
            $this->syncFormats($book, $request->validated('formats'));

            return $book;
        });

        return redirect()->route('books.show', $book)->with('status', __('Book saved.'));
    }

    public function show(Book $book): View
    {
        return view('books.show', ['book' => $book->load(['formats.paperType', 'party'])]);
    }

    public function edit(Book $book): View
    {
        return $this->form($book->load('formats'));
    }

    public function update(BookRequest $request, Book $book): RedirectResponse
    {
        DB::transaction(function () use ($request, $book): void {
            $book->update($this->bookAttributes($request));
            $this->syncFormats($book, $request->validated('formats'));
        });

        return redirect()->route('books.show', $book)->with('status', __('Book saved.'));
    }

    public function destroy(Book $book): RedirectResponse
    {
        if ($book->formats()->has('workOrders')->exists()) {
            return back()->withErrors(['book' => __(':name has work orders. Deactivate it instead so the orders keep their book.', ['name' => $book->name])]);
        }

        $book->delete();

        return redirect()->route('books.index')->with('status', __('Book :name deleted.', ['name' => $book->name]));
    }

    private function form(Book $book): View
    {
        return view('books.form', [
            'book' => $book,
            'paperTypes' => PaperType::query()->active()->orderBy('code')->get(),
            'publishers' => Party::query()->active()->whereIn('kind', [PartyKind::PaperParty, PartyKind::Other])->orderBy('name')->get(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookAttributes(BookRequest $request): array
    {
        return $request->safe()->except('formats') + ['is_active' => $request->boolean('is_active')];
    }

    /**
     * Update formats that came back with an id, create new rows, and delete rows the user removed.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncFormats(Book $book, array $rows): void
    {
        $keep = [];

        foreach ($rows as $row) {
            $attributes = collect($row)->except('id')->map(fn ($value) => $value === '' ? null : $value)->all();
            $format = ! empty($row['id']) ? $book->formats()->find($row['id']) : null;

            $format ? $format->update($attributes) : $format = $book->formats()->create($attributes);
            $keep[] = $format->id;
        }

        $book->formats()->whereNotIn('id', $keep)->delete();
    }
}

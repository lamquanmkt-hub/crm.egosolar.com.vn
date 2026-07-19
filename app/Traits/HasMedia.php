<?php
namespace App\Traits;
use App\Models\Media\MediaFile;
use App\Models\Media\MediaRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasMedia
{
	/**
	 * Lấy tất cả media gắn với model
	 * @return MorphMany
	 */
	public function allMedia(): MorphMany {
		return $this->morphMany(MediaRelation::class, 'model')->with('media.metadata');
	}

	/**
	 * Lấy media theo usage_type (vd: gallery, main_image)
	 * @param string $usage
	 *
	 * @return MorphMany
	 */
	public function mediaRelations(string $usage): MorphMany {
		return $this->morphMany(MediaRelation::class, 'model')
		            ->where('usage_type', $usage)
		            ->with('media');
	}

	/**
	 * Lấy một media theo usage_type
	 * @param string $usage
	 *
	 * @return MorphOne
	 */
	public function mediaRelation(string $usage): MorphOne {
		return $this->morphOne(MediaRelation::class, 'model')
		            ->where('usage_type', $usage)
		            ->with('media');
	}
	/**
	 * Gắn file media đã tồn tại (media_id) vào model
	 * @param MediaFile $mediaFile
	 * @param string $usage
	 * @param int $sort
	 *
	 * @return Model
	 */
	public function attachMedia(MediaFile $mediaFile, string $usage = 'default', int $sort = 0, $userId = null): Model {
		return $this->morphMany(MediaRelation::class, 'model')->create([
			'media_id'   => $mediaFile->id,
			'usage_type' => $usage,
			'sort_order' => $sort,
			'attached_by' => $userId ?? auth()->id(),
		]);
	}

	/**
	 * @param MediaFile $file
	 *
	 * @return mixed
	 */
	public function detachMedia(MediaFile $file)
	{
		return MediaRelation::where('media_id', $file->id)
		                    ->where('model_type', get_class($this))
		                    ->where('model_id', $this->getKey())
		                    ->delete();
	}
	public function detachMediaById(int $relationId)
	{
		return $this->morphMany(MediaRelation::class, 'model')->where('id', $relationId)->delete();
	}
}

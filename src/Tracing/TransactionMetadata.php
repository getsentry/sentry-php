<?php

namespace Sentry\Tracing;

final class TransactionMetadata
{
    /**
     * @var float|int|null
     */
    private $samplingRate;

    /**
     * @var TransactionSamplingMethod|null
     */
    private $samplingMethod;

    /**
     * @var Baggage|null
     */
    private $baggage;

    /**
     * @var string|null
     */
    private $requestPath;

    /**
     * @var TransactionSource|null
     */
    private $source;

    /**
     * @return float|int|null
     */
    public function getSamplingRate()
    {
        return $this->samplingRate;
    }

    /**
     * @param float|int|null $samplingRate
     */
    public function setSamplingRate($samplingRate): void
    {
        $this->samplingRate = $samplingRate;
    }

    /**
     * @return TransactionSamplingMethod|null
     */
    public function getSamplingMethod(): ?TransactionSamplingMethod
    {
        return $this->samplingMethod;
    }

    /**
     * @param TransactionSamplingMethod|null $samplingMethod
     */
    public function setSamplingMethod(?TransactionSamplingMethod $samplingMethod): void
    {
        $this->samplingMethod = $samplingMethod;
    }

    /**
     * @return Baggage|null
     */
    public function getBaggage(): ?Baggage
    {
        return $this->baggage;
    }

    /**
     * @param Baggage|null $baggage
     */
    public function setBaggage(?Baggage $baggage): void
    {
        $this->baggage = $baggage;
    }

    /**
     * @return string|null
     */
    public function getRequestPath(): ?string
    {
        return $this->requestPath;
    }

    /**
     * @param string|null $requestPath
     */
    public function setRequestPath(?string $requestPath): void
    {
        $this->requestPath = $requestPath;
    }

    /**
     * @return TransactionSource|null
     */
    public function getSource(): ?TransactionSource
    {
        return $this->source;
    }

    /**
     * @param TransactionSource|null $source
     */
    public function setSource(?TransactionSource $source): void
    {
        $this->source = $source;
    }
}
